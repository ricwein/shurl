<?php

namespace ricwein\shurl\core;

use ricwein\shurl\config\Config;

/**
 * create alpha-numeric ids from an input
 */
class IDEngine {

	/**
	 * @var Config
	 */
	protected $_config;

	/**
	 * @param Config $config
	 */
	public function __construct(Config $config) {
		$this->_config = $config;
	}

	/**
	 * create pseudo-random alpha-numeric ID for given input
	 * @param  string $input
	 * @return string
	 */
	public function create(string $input): string{

		$alphabet = $this->_config->slug['alphabet'];
		$base     = static::_strlen($alphabet);
		$input    = hexdec(hash($this->_config->slug['hash'], $input));

		$result = '';
		for ($counter = ($input !== 0 ? floor(log($input, $base)) : 0); $counter >= 0; $counter--) {
			$bcp    = bcpow($base, $counter);
			$a      = floor($input / $bcp) % $base;
			$result = $result . substr($alphabet, $a, 1);
			$input  = $input - ($a * $bcp);
		}

		return $result;
	}

	/**
	 * @param string $string
	 * @return int
	 * @throws \RuntimeException
	 */
	protected static function _strlen(string $string): int {

		/**
		 * @var mixed
		 */
		static $mbAvailable = null;
		if ($mbAvailable === null) {
			$mbAvailable = \is_callable('\\mb_strlen');
		}

		if ($mbAvailable && false !== $length = \mb_strlen($string, '8bit')) {
			return $length;
		} elseif (false !== $length = \strlen($string)) {
			return $length;
		}

		throw new \RuntimeException('unable to fetch string-length', 500);
	}
}
