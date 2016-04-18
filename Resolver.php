<?php


/**
 * Tweet token. Token is a part of the tweet with some meaning.
 */
abstract class Token
{
	public $str;

	public $type;

	public function __construct($str)
	{
		$this->str = $str;
	}

	public function __toString()
	{
		return get_class($this) . '[' . json_encode($this->str) . ']';
	}
}


/**
 * Word token. Represents a single word, or a phrase.
 * Can be minified.
 */
class WordToken extends Token
{
	/** @var string available substitutions */
	public $options;

	public function __construct($str)
	{
		parent::__construct($str);
		$this->type = "word";

		$this->options = [$str]; // TODO other options
	}
}


/**
 * Whitespace / punctuation token.
 * This token should be left alone, as it can contain emojis and other strange stuff.
 * Newlines and spaces are normalized at creation.
 */
class FillerToken extends Token
{
	public function __construct($str)
	{
		$str = preg_replace('/[ ]{2,}/', ' ', $str);
		$str = preg_replace('/[ ]+\n/m', "\n", $str);

		parent::__construct($str);
		$this->type = "fill";
	}
}


/**
 * Special token representing a #hashtag, @handle or a URL.
 * The content must be left exactly as is.
 */
class SpecialToken extends Token
{
	public $length;
	public $kind;

	public function __construct($str, $kind = null)
	{
		parent::__construct($str);
		$this->type = "special";
		$this->kind = $kind;
		$this->length = mb_strlen($str);

		if ($kind == 'url') $this->length = 23;
	}
}


/**
 * Tweet parser & minifier. This is the main class of TwitMin.
 */
class Resolver
{
	private static $alternatives = null;

	/** @var Token[] */
	public $tokens = [];
	public $orig = null;
	public $totalLength = 0;

	private $linkLenAdjust;

	private $wordbuf = '';

	private $coll = null;

	private static $ligatures = [
		'...' => '…',
		'AA' => 'Ꜳ',
		'aa' => 'ꜳ',
		'AE' => 'Æ',
		'ae' => 'æ',
		'AO' => 'Ꜵ',
		'ao' => 'ꜵ',
		'AU' => 'Ꜷ',
		'au' => 'ꜷ',
		'AV' => 'Ꜹ',
		'av' => 'ꜹ',
		'AY' => 'Ꜽ',
		'ay' => 'ꜽ',
		'DZ' => 'Ǳ',
		'Dz' => 'ǲ',
		'dz' => 'ǳ',
		'DŽ' => 'Ǆ',
		'Dž' => 'ǅ',
		'dž' => 'ǆ',
		'ffi' => 'ﬃ',
		'ffl' => 'ﬄ',
		'ff' => 'ﬀ',
		'fi' => 'ﬁ',
		'fl' => 'ﬂ',
		'IJ' => 'Ĳ',
		'ij' => 'ĳ',
		'LJ' => 'Ǉ',
		'Lj' => 'ǈ',
		'lj' => 'ǉ',
		'NJ' => 'Ǌ',
		'Nj' => 'ǋ',
		'nj' => 'ǌ',
		'OE' => 'Œ',
		'OO' => 'Ꝏ',
		'oo' => 'ꝏ',
		'ft' => 'ﬅ',
		'ue' => 'ᵫ' //weird looking in twitter
	];


	public function __construct()
	{
		if (self::$alternatives == null) {
			self::$alternatives = require('data.php');
		}
	}


	public function process($tweet, $opts = [])
	{
		$opts = array_merge([
			'noegg' => false,
			'ligatures' => true,
			'phrases' => true
		], $opts);

		// make sure mb_* work right (sort of)
		setlocale(LC_CTYPE, 'EN_us.UTF-8');

		// prepare the input
		$tweet = trim($tweet);
		$tweet = preg_replace("/\r\n/", "\n", $tweet);

		$this->orig = $tweet;

		if (!$opts['noegg']) {
			// *** Safeguards ***
			// Blame @mvilcis. Thanks @freundTech for testing.
			$tweet = preg_replace_callback('/(\b(?:gnu\/|arch|)linux)(\s+)(is\s+(?:really\s+)?(?:bad|awful|terrible)|sucks(?:\s+dick|\s+balls|))\b/im', function ($m) {
				$linux = $m[1];
				if (strtolower($linux) == 'linux') {
					$linux = ($linux == 'LINUX') ? 'GNU/LINUX' : 'GNU/Linux';
				}
				$space = $m[2];
				$isWhat = $m[3];
				$isGr8 = (strtoupper($isWhat) == $isWhat) ? 'IS GREAT' : 'is great';
				return $linux . $space . $isGr8;
			}, $tweet);
		}

		$chars = preg_split('//u', $tweet, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $ch) {
			// 3 attempts
			for ($i = 0; $i < 3; $i++) {
				if ($this->processChar($ch)) break;
			}
		}

		if (count($this->wordbuf)) {
			switch ($this->coll) {
				case 'email':
				case 'hash':
				case 'url':
				case 'handle':
					$this->addToken(new SpecialToken($this->wordbuf, $this->coll));
					break;

				case 'junk':
					$this->addToken(new FillerToken($this->wordbuf));
					break;

				case null:
					$this->addToken(new WordToken($this->wordbuf));
					break;
			}
		}

		if ($opts['phrases']) $this->combinePhrases();
		$this->findAlternatives();

		if ($opts['ligatures']) $this->applyLigatures();

		$this->makeShort();

		$this->totalLength = mb_strlen($this->orig) - $this->linkLenAdjust;
	}

	private function applyLigatures()
	{
		foreach ($this->tokens as &$tok) {
			if ($tok instanceof WordToken) {
				//echo get_class($tok)."\n";
				foreach ($tok->options as $i => $opt) {
					$opt = str_replace(array_keys(self::$ligatures), array_values(self::$ligatures), $opt);
					$tok->options[$i] = $opt;
					//echo $opt;
				}

				if (!in_array($tok->str, $tok->options)) {
					$tok->options[] = $tok->str;
				}
			}
		}
	}

	private function combinePhrases()
	{
		// FIXME this is really slow

		$phr = array_filter(self::$alternatives, function($a) {
			return (strpos($a, ' ') !== false);
		}, ARRAY_FILTER_USE_KEY);

		foreach (array_keys($phr) as $phrase) {
			$words = explode(' ', $phrase);
			$new_toks = [];
			$ticks = 0;
			$w_idx = 0;
			$next_space = false;

			$buffered = [];
			foreach ($this->tokens as $t) {
				$want_reset = false;

				if ($ticks == 0 && !($t instanceof WordToken)) {
					$new_toks[] = $t;
					continue;
				}

				do { // once
					if ($next_space) {
						if (!($t instanceof FillerToken)) {
							$buffered[] = $t;
							$want_reset = true;
							break;
						}

						if (trim($t->str) === '') {
							$next_space = false;
							$ticks++;
							$buffered[] = $t;
							continue;
						}
					}

					if ($t instanceof WordToken) {
						//echo "word char! ";
						if (mb_strtolower($t->str) == $words[$w_idx]) {
							$next_space = true;
							$ticks++;
							$w_idx++;
							$buffered[] = $t;

							if ($w_idx >= count($words)) {
								// we found a match

								// collect original
								$original = array_reduce($buffered, function($carry, $x) {
									return $carry . $x->str;
								}, '');

								$new_toks[] = new WordToken($original);
								$buffered = [];
								$want_reset = true;
								break;
							}
						} else {
							// wrong word
							$buffered[] = $t;
							$want_reset = true;
							break;
						}
					} else {
						// expected word, got other
						$buffered[] = $t;
						$want_reset = true;
						break;
					}
				} while(0);

				if ($want_reset) {
					$ticks = 0;
					$w_idx=0;
					$next_space = false;
					foreach ($buffered as $b) {
						$new_toks[] = $b;
					}
					$buffered = [];
				}
			}

			if (count($buffered)) {
				foreach ($buffered as $b) {
					$new_toks[] = $b;
				}
			}

			$this->tokens = $new_toks;
		}
	}

	/** Change case to match template (where possible) */
	private static function adjustCase($alts, $str)
	{
		if ($str == mb_strtoupper($str)) {
			// all caps
			return array_map('mb_strtoupper', $alts);
		}

		$first = mb_substr($str, 0, 1);
		$second = mb_substr($str, 1, 1);
		if (mb_strtoupper($first) == $first && mb_strtolower($second) == $second) {
			// first is upper
			return array_map(function($x) {
				return mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1);
			}, $alts);
		}

		return $alts;
	}

	/** Sort alternatives by length */
	private function makeShort()
	{
		foreach ($this->tokens as $i => $t) {
			if ($t instanceof WordToken) {
				usort($t->options, function($a, $b) {
					return mb_strlen($a) - mb_strlen($b);
				});
			}
		}
	}

	/** Attach alternatives to words */
	private function findAlternatives()
	{
		foreach ($this->tokens as $i => $t) {
			if ($t instanceof WordToken) {
				$search = mb_strtolower($t->str);

				if (array_key_exists($search, self::$alternatives)) {
					$alts = self::$alternatives[$search];
					if (!is_array($alts)) $alts = [$alts];
					$t->options = array_merge($t->options, self::adjustCase($alts, $t->str));
				}
			}
		}
	}

	private function endToken()
	{
		$this->wordbuf = ''; // clear the collecting buffer
		$this->coll = null;
	}

	private function addToken(Token $t)
	{
		if ($t instanceof WordToken && $t->str[strlen($t->str)-1]=='\'') {
			$t->str = rtrim($t->str, '\'');
			$this->tokens[] = $t;

			$this->tokens[] = new FillerToken('\''); // ideally would be joined to the following, but w/e
		} else {
			$this->tokens[] = $t;
		}

		if ($t instanceof SpecialToken) {
			$this->linkLenAdjust += mb_strlen($t->str) - $t->length;
		}

		$this->endToken();
	}

	private static function wordChar($ch) {
		return ctype_alnum($ch) || in_array($ch, ['\'', '-', '/']);
	}

	private static function handleChar($ch) {
		return ctype_alnum($ch) || in_array($ch, ['_']);
	}

	private static function urlChar($ch) {
		return ctype_alnum($ch) || in_array($ch, ['_', '-', '.', '/', '#', '%', '=', '?', '!']);
	}

	private static function emailChar($ch) {
		return ctype_alnum($ch) || in_array($ch, ['_', '-', '.']);
	}

	private function processChar($ch)
	{
		if (in_array($this->coll, ['hash', 'handle'])) {
			if (!self::handleChar($ch)) {
				// end of hashtag or name
				$this->addToken(new SpecialToken($this->wordbuf, $this->coll));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['url'])) {
			if (!self::urlChar($ch)) {
				$this->addToken(new SpecialToken($this->wordbuf, $this->coll));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['email'])) {
			if (!self::emailChar($ch)) {
				$this->addToken(new SpecialToken($this->wordbuf, $this->coll));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['junk'])) {
			if ((self::wordChar($ch) &  $ch != '\'') || in_array($ch, ['#', '@'])) {
				// end of junk, start of good stuff
				$this->addToken(new FillerToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (self::wordChar($ch)) {
			if ($this->coll == 'junk') {
				// end of junk
				$this->addToken(new FillerToken($this->wordbuf));
			}

			// we have a alnum char - can be a word, or perhaps url or e-mail
			$this->wordbuf .= $ch;
			return true;
		}

		if ($ch == '@') {
			if (strlen($this->wordbuf)) {
				// we have a e-mail
				$this->coll = 'email';
			} else {
				$this->coll = 'handle';
			}

			$this->wordbuf .= $ch;
			return true;
		}

		if ($ch == '#') {
			if (strlen($this->wordbuf) == 0) {
				// we have a hashtag
				$this->coll = 'hash';
			}

			$this->wordbuf .= $ch;
			return true;
		}

		if ($ch == ':') {
			if (in_array($this->wordbuf, ['http', 'https'])) {
				// we have a url
				$this->coll = 'url'; // continue with the buffer
			} else {
				// start of a junk section
				if (strlen($this->wordbuf)) {
					$this->addToken(new WordToken($this->wordbuf));
				}

				$this->coll = 'junk';
			}

			$this->wordbuf .= $ch;
			return true;
		}

		// junk starts
		if (mb_strlen($this->wordbuf)) {
			$this->addToken(new WordToken($this->wordbuf));
		}

		$this->coll = 'junk';
		$this->wordbuf .= $ch;
		return true;
	}
}
