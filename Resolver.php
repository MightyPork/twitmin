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
		'fuck you',
		'new york',
		'european union',
		'new zealand',
		'united kingdom',
		'see you',
		'bye bye',
		'what the fuck',
	];

	private $alternatives = [
		'you' => ['u', 'yo'],
		'your' => ['yo', 'ur'],
		'before' => ['be4'],
		'to' => ['2'],
		'one' => ['1'],
		'is not' => ["ain't", "isn't"],
		'are not' => ["ain't", "aren't"],
		'am not' => ["ain't"],
		'i am' => ["I'm"],
		'you are' => ["you're", "ur"],
		'mate' => ["m8"],
		'great' => ["gr8"],
		'bait' => ["b8"],
		'bye bye' => ["bb"],
		'see you' => ["cya"],
		'fuck you' => ["fu"],
		'you all' => ["ya'll"],
		'what the fuck' => ["wtf"],
		'homosexual' => ["gay"],
		'faggot' => ["gay", "fag"],
		'faggots' => ["gays", "fags"],
		'arse' => ["ass"],
		'going to' => ["gonna"],
		'got to' => ["gotta"],
		'want to' => ["wanna"],
		'united states' => ["US"],
		'european union' => ["EU"],
		'new zealand' => ["NZ"],
		'united kingdom' => ["UK"],
		'with' => ["w/"],
		'without' => ["w/o"],
	];

	/** @var Token[] */
	public $tokens = [];

	private $wordbuf = '';

	private $coll = null;

	public function process($tweet)
	{
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
	}

	private function combinePhrases()
	{

	}

	private function findAlternatives()
	{

	}

	private function endToken()
	{
		$this->wordbuf = ''; // clear the collecting buffer
		$this->coll = null;
	}

	private function addToken(Token $t)
	{
		$this->tokens[] = $t;
		$this->endToken();
	}

	private function processChar($ch)
	{
		if (in_array($this->coll, ['hash', 'handle'])) {
			if (!ctype_alnum($ch) && $ch != '_') {
				// end of hashtag or name
				$this->addToken(new SpecialToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['url', 'email'])) {
			if (in_array($ch, ["\n", "\r", " ", "\t"])) {
				// whitespace
				$this->addToken(new SpecialToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (in_array($this->coll, ['junk'])) {
			if (ctype_alnum($ch) || in_array($ch, ['#', '@'])) {
				// end of junk, start of good stuff
				$this->addToken(new FillerToken($this->wordbuf));
				return false;
			} else {
				$this->wordbuf .= $ch; // append it
				return true;
			}
		}

		if (ctype_alnum($ch)) {
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

		if ($ch == '.') {
			// assume we have a URL
			$this->coll = 'url';
			$this->wordbuf .= $ch;
			return true;
		}

		// junk starts
		if (mb_strlen($this->wordbuf)) {
			$this->addToken(new WordToken($this->wordbuf));
		}

		$this->coll = 'junk';
		$this->wordbuf .= $ch;
	}
}
