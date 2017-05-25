<?php

namespace ricwein\shurl\Core;

use ricwein\shurl\Config\Config;

/**
 * simple Template parser
 */
class Template {

	/**
	 * @var int
	 */
	const MAX_DEPTH = 64;

	/**
	 * @var string[]
	 */
	const REGEX = ['/\{\{\s*', '\s*\}\}/'];

	/**
	 * @var string
	 */
	protected $_templateFile;

	/**
	 * @var Config
	 */
	protected $_config;

	/**
	 * @var Network
	 */
	protected $_network;

	/**
	 * @param string $templateFile
	 * @param Config $config
	 * @param Network $network
	 */
	public function __construct(string $templateFile, Config $config, Network $network) {
		if (false === $templatePath = realpath(__DIR__ . '/../../' . trim($config->template['path'], '/'))) {
			throw new \UnexpectedValueException('template path not found', 404);
		}

		$extension    = ltrim($config->template['extension'], '.');
		$templateFile = $templatePath . '/' . trim(basename($templateFile, $extension), '/') . '.' . $extension;

		if (!file_exists($templateFile)) {
			die($templateFile);
			throw new \UnexpectedValueException('template not found', 404);
		} elseif (!is_readable($templateFile)) {
			throw new \UnexpectedValueException('unable to read template', 404);
		}

		$this->_templateFile = $templateFile;

		$this->_config  = $config;
		$this->_network = $network;
	}

	/**
	 * @param array|object $bindings
	 * @return void
	 */
	public function render($bindings = null) {

		if (false === $content = @file_get_contents($this->_templateFile)) {
			throw new \UnexpectedValueException('unable to read template', 404);
		}

		if ($bindings !== null) {
			$content = $this->_apply($content, $bindings);
		}

		$content = $this->_apply($content, array_merge([
			'base_url' => $this->_network->getBaseURL(),
		], $this->_config->template['defaultBindings']));

		echo $content;
		exit(0);
	}

	/**
	 * @param string $content
	 * @param array|object $bindings varaibles to be replaced
	 * @param int $currentDepth
	 * @return string
	 */
	protected function _apply(string $content, $bindings, int $currentDepth = 0): string {

		// iterate through all values
		foreach ($bindings as $key => $value) {
			if ($value === null || is_scalar($value)) {

				// replace values if matching with the following excaped keys
				$content = $this->_strReplace($key, $value, $content);

			} elseif ((is_array($value) || is_object($value)) && $currentDepth <= self::MAX_DEPTH) {

				// recursive call to apply() if value is iteraterable
				$content = $this->_apply($content, $value, false, $currentDepth + 1);

			} else {

				// catch all other data-types
				$content = $this->_strReplace($key, '\'' . gettype($value) . '\'', $content);

			}
		}

		return $content;
	}

	/**
	 * replace inline variables in string through values
	 * supports twig, shell and swift string-variables
	 * @param int|float|string $key
	 * @param int|float|string $value
	 * @param string $string
	 * @return string
	 */
	protected function _strReplace($key, $value, string $string): string{

		// mitigate null-byte attacks
		$key = str_replace(chr(0), '', $key);

		return (string) trim(preg_replace(implode($key, static::REGEX), (string) $value, $string));
	}

}
