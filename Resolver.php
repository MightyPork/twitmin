<?php


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


class WordToken extends Token
{
	public $options;

	public function __construct($str)
	{
		parent::__construct($str);
		$this->type = "word";

		$this->options = [$str]; // TODO other options
	}
}


class FillerToken extends Token
{
	public function __construct($str)
	{
		$str = preg_replace('/\s+/', ' ', $str);

		parent::__construct($str);
		$this->type = "fill";
	}
}


class SpecialToken extends Token
{
	public $length;

	public function __construct($str)
	{
		parent::__construct($str);
		$this->type = "special";
		$this->length = mb_strlen($str);
	}
}


class Resolver
{
	private $phrases = [
		'you all',
		'i am',
		'it is',
		'is not',
		'are not',
		'am not',
		'it will',
		'you are',
		'fuck you',
		'you will',
		'i will',
		'new york',
		'european union',
		'united states',
		'new zealand',
		'united kingdom',
		'see you',
		'bye bye',
		'what the fuck',
		'cell phone',
		'going to',
		'want to',
		"don't know",
	];

	private $alternatives = [
		"don't know" => ['dunno'],
		'very' => ['v'],
		'please' => ['pls'],
		'though' => ['tho'],
		'through' => ['thru'],
		'you' => ['u', 'yo'],
		'your' => ['yo', 'ur'],
		'before' => ['be4'],
		'to' => ['2'],
		'one' => ['1'],
		'it is' => ["it's"],
		'is not' => ["ain't", "isn't"],
		'are not' => ["ain't", "aren't"],
		'it will' => ["it'll"],
		'am not' => ["ain't"],
		'i am' => ["I'm"],
		'are' => ["r"],
		'for' => ["4"],
		'and' => ["&"],
		'application' => ["app"],
		'applications' => ["apps"],
		'you are' => ["you're", "ur"],
		'new york' => ["NY"],
		'mate' => ["m8"],
		'great' => ["gr8"],
		'bait' => ["b8"],
		'bye bye' => ["bb"],
		'see you' => ["cya"],
		'fuck you' => ["fu"],
		'fuck' => ["fk", "f"],
		'expensive' => ["pricey"],
		'localisation' => ["l10n"],
		'internationalisation' => ["i18n"],
		'international' => ["int'l"],
		'vessel' => ["ship"],
		'baby' => ["bby"],
		'girlfriend' => ["gf"],
		'boyfriend' => ["bf"],
		'once' => ["1x"],
		'twice' => ["2x"],
		'be' => ["b"],
		'software' => ["SW"],
		'firmware' => ["FW"],
		'hardware' => ["HW"],
		'corporation' => ["corp"],
		'technology' => ["tech"],
		'language' => ["lang"],
		'you all' => ["y'all"],
		"you'll" => ["u'll"],
		"years" => ["yrs"],
		"year" => ["yr"],
		"keyboard" => ["kybd"],
		"computer" => ["PC"],
		"anyone" => ["any1"],
		"something" => ["sth"],
		"affordable" => ["cheap"],
		"additions" => ["extras"],
		"comfortable" => ["comfy"],
		"why" => ["y"],
		"you will" => ["you'll", "u'll"],
		"i will" => ["I'll", "imma"],
		'what the fuck' => ["wtf"],
		'whatever' => ["w/e"],
		'probably' => ["prolly"],
		'homosexual' => ["gay"],
		'faggot' => ["gay", "fag"],
		'faggots' => ["gays", "fags"],
		'arse' => ["ass"],
		'going to' => ["gonna"],
		'got to' => ["gotta"],
		'want to' => ["wanna"],
		'united states' => ["US", "'murica", "murica"],
		'american' => ["'murican","murican"],
		'european union' => ["EU"],
		'new zealand' => ["NZ"],
		'united kingdom' => ["UK"],
		'with' => ["w/"],
		'without' => ["w/o"],
		'little' => ["lil"],
		'girl' => ["gal"],
		'really' => ["rly"],
		'microsoft' => ["M$"],
		'cell phone' => ["phone"],
	];

	/** @var Token[] */
	public $tokens = [];

	private $wordbuf = '';

	private $coll = null;

	public function process($tweet)
	{
		// conditioning
		$tweet = trim($tweet);
		$tweet = str_replace('...', '…', $tweet);

		setlocale(LC_CTYPE, 'EN_us.UTF-8');

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
					$t = new SpecialToken($this->wordbuf);
					if ($this->coll == 'url') $t->length = 23; // https://t.co/0123456789
					$this->addToken($t);
					break;

				case 'junk':
					$this->addToken(new FillerToken($this->wordbuf));
					break;

				case null:
					$this->addToken(new WordToken($this->wordbuf));
					break;
			}
		}

		$this->combinePhrases();
		$this->findAlternatives();
		$this->makeShort();
	}

	private function combinePhrases()
	{
		foreach ($this->phrases as $phrase) {
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
							//echo "FIALTOK";
							$buffered[] = $t;
							$want_reset = true;
							break;
						}

						if (trim($t->str) === '') {
							//echo "FILLER_OK ";
							$next_space = false;
							$ticks++;
							$buffered[] = $t;
							continue;
						}
					}

					if ($t instanceof WordToken) {
						//echo "WC! ";
						if (mb_strtolower($t->str) == $words[$w_idx]) {
							$next_space = true;
							$ticks++;
							$w_idx++;
							$buffered[] = $t;

							//echo "MATCH! ";

							if ($w_idx >= count($words)) {
								// we found a match
								$new_toks[] = new WordToken($phrase); // TODO adjust capitalisation
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

	private function findAlternatives()
	{
		foreach ($this->tokens as $i => $t) {
			if ($t instanceof WordToken) {
				$search = mb_strtolower($t->str);

				if (array_key_exists($search, $this->alternatives)) {
					$t->options = array_merge($t->options, $this->alternatives[$search]);
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

		$this->endToken();
	}

	private static function wordChar($ch) {
		return ctype_alnum($ch) || in_array($ch, ['\'', '-']);
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
				$this->addToken(new SpecialToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['url'])) {
			if (!self::urlChar($ch)) {
				$this->addToken(new SpecialToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['email'])) {
			if (!self::emailChar($ch)) {
				$this->addToken(new SpecialToken($this->wordbuf));
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
