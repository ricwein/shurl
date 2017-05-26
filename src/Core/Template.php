<?php

namespace ricwein\shurl\Core;

use ricwein\shurl\Config\Config;

/**
 * simple Template parser with Twig-like syntax
 */
class Template {

	/**
	 * @var int
	 */
	const MAX_DEPTH = 64;

	/**
	 * @var string[]
	 */
	const REGEX_VARIABLES = ['/\{\{\s*', '\s*\}\}/'];

	/**
	 * @var string[]
	 */
	const REGEX_METHODS = ['/\{%\s*', '\s*%\}/'];

	/**
	 * @var string
	 */
	protected $_templatePath;

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
	 * @var Cache
	 */
	protected $_cache = null;

	/**
	 * @param string $templateFile
	 * @param Config $config
	 * @param Network $network
	 * @param Cache|null $cache
	 */
	public function __construct(string $templateFile, Config $config, Network $network, Cache $cache = null) {
		if (false === $templatePath = realpath(__DIR__ . '/../../' . trim($config->template['path'], '/'))) {
			throw new \UnexpectedValueException('template path not found', 404);
		}

		$this->_config  = $config;
		$this->_network = $network;
		$this->_cache   = $cache;

		$this->_templatePath = $templatePath . '/';
		$this->_templateFile = $this->_getFilePath($templateFile);
	}

	/**
	 * @param string $filename
	 * @return string
	 */
	protected function _getFilePath(string $filename): string{
		$extension = ltrim($this->_config->template['extension'], '.');
		$fileNames = [];

		// name defined in routes, look them up
		if (isset($this->_config->template['route'][$filename])) {
			$fileNames[] = $this->_config->template['route'][$filename];
			$fileNames[] = trim(dirname($this->_config->template['route'][$filename]) . '/' . pathinfo($this->_config->template['route'][$filename], PATHINFO_FILENAME), '/.') . '.' . $extension;
		}

		// default lookup for filenames, with and without default extension
		$fileNames[] = $filename;
		$fileNames[] = trim(dirname($filename) . '/' . pathinfo($filename, PATHINFO_FILENAME), '/.') . '.' . $extension;

		// try each possible filename/path to find valid file
		foreach ($fileNames as $file) {
			if (file_exists($this->_templatePath . '/' . $file) && is_readable($this->_templatePath . '/' . $file)) {
				return $file;
			}
		}

		throw new \UnexpectedValueException(sprintf('no template found for \'%s\'', $filename), 404);
	}

	/**
	 * load and read file
	 * @param  string $filePath
	 * @return string
	 */
	protected function _readFile(string $filePath): string {
		if (false !== $content = @file_get_contents($this->_templatePath . $filePath)) {
			return $content;
		}

		throw new \UnexpectedValueException('unable to read template', 404);
	}

	/**
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return void
	 */
	public function render($bindings = null, callable $filter = null) {

		$content = $this->make($bindings, $filter);

		echo $content;
		exit(0);
	}

	/**
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return string
	 */
	public function make($bindings = null, callable $filter = null): string {

		if ($this->_cache !== null) {

			$templateCache = $this->_cache->getItem('template_' . str_replace(
				['{', '}', '(', ')', '/', '\\', '@', ':'],
				['|', '|', '|', '|', '.', '.', '-', '_'],
				$this->_templatePath . $this->_templateFile
			) . ($this->_config->template['useFileHash'] ? hash_file($this->_config->template['useFileHash'], $this->_templatePath . $this->_templateFile) : ''));

			if (null === $content = $templateCache->get()) {

				// load template from file
				$content = $this->_readFile($this->_templateFile);
				$content = $this->_applyMethods($content);

				$templateCache->set($content);
				$templateCache->expiresAfter($this->_config->cache['duration']);
				$this->_cache->save($templateCache);
			}

		} else {

			// load template from file
			$content = $this->_readFile($this->_templateFile);
			$content = $this->_applyMethods($content);

		}

		// apply custom variable-bindings
		$content = $this->_applyBindings($content, $bindings);

		// apply default variable-bindings
		$content = $this->_applyBindings($content, array_merge([
			'base_url' => $this->_network->getBaseURL(),
			'name'     => ucfirst(strtolower(str_replace(['_', '.'], ' ', pathinfo(str_replace($this->_config->template['extension'], '', $this->_templateFile), PATHINFO_FILENAME)))),
		], $this->_config->template['defaultBindings']));

		// run user-defined filters above content
		if ($filter !== null) {
			$content = call_user_func_array($filter, [$content, $this]);
		}

		return $content;
	}

	/**
	 * @param string $content
	 * @param int $currentDepth
	 * @return string
	 */
	protected function _applyMethods(string $content, int $currentDepth = 0): string{

		// include other template files
		$content = preg_replace_callback(implode('include(.*)', static::REGEX_METHODS), function ($match) use ($currentDepth) {
			$filecontent = $this->_readFile($this->_getFilePath(trim($match[1], '\'" ')));
			if ($currentDepth <= self::MAX_DEPTH) {
				return $this->_applyMethods($filecontent, $currentDepth + 1);
			}
			return $filecontent;
		}, $content);

		return $content;
	}

	/**
	 * @param string $content
	 * @param array|object|null $bindings varaibles to be replaced
	 * @param int $currentDepth
	 * @return string
	 */
	protected function _applyBindings(string $content, $bindings = null, int $currentDepth = 0): string {

		if ($bindings === null) {
			return $content;
		}

		// iterate through all values
		foreach ($bindings as $key => $value) {
			if ($value === null || is_scalar($value)) {

				// replace values if matching with the following excaped keys
				$content = $this->_strReplace($key, $value, $content);

			} elseif (is_object($value) && method_exists($value, $key)) {

				// replace variable with result of methods
				$content = $this->_strReplace($key, call_user_func_array([$value, $key], []), $content);

			} elseif ((is_array($value) || is_object($value)) && $currentDepth <= self::MAX_DEPTH) {

				// recursive call to apply() if value is iteraterable
				$content = $this->_applyBindings($content, $value, false, $currentDepth + 1);

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

		return (string) trim(preg_replace(implode($key, static::REGEX_VARIABLES), (string) $value, $string));
	}

	/**
	 * @return Network
	 */
	public function network(): Network {
		return $this->_network;
	}

	/**
	 * @return Config
	 */
	public function config(): Config {
		return $this->_config;
	}

}
