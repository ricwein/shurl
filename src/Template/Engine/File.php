<?php

namespace ricwein\shurl\Template\Engine;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Exception\NotFound;

/**
 * provide File interaction methods
 */
class File {

	/**
	 * @var string
	 */
	protected $_basepath;

	/**
	 * @var Config
	 */
	protected $_config;

	/**
	 * @param string $basepath
	 * @param Config $config
	 */
	public function __construct(string $basepath, Config $config) {
		$this->_basepath = rtrim($basepath, '/') . '/';
		$this->_config   = $config;
	}

	/**
	 * @param  string $filePath
	 * @param bool $searchPath
	 * @return string
	 * @throws NotFound
	 */
	public function read(string $filePath, bool $searchPath = false): string {
		if ($searchPath) {
			$filePath = $this->path($filePath);
		}

		if (false !== $content = @file_get_contents($this->_basepath . $filePath)) {
			return $content;
		}

		throw new NotFound('unable to read template', 404);
	}

	/**
	 * @param string $filename
	 * @return string
	 * @throws NotFound
	 */
	public function path(string $filename): string{
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
			if (file_exists($this->_basepath . '/' . $file) && is_readable($this->_basepath . '/' . $file)) {
				return $file;
			}
		}

		throw new NotFound(sprintf('no template found for \'%s\'', $filename), 404);
	}

	/**
	 * @param string $filename
	 * @return string
	 * @throws \UnexpectedValueException
	 */
	public function cachePath(string $filename): string{
		$path = $this->_basepath . $this->path($filename);
		return str_replace(
			['{', '}', '(', ')', '/', '\\', '@', ':'],
			['|', '|', '|', '|', '.', '.', '-', '_'],
			$path
		);
	}

	/**
	 * @param string $filename
	 * @return string
	 */
	public function hash(string $filename): string {
		if (!$this->_config->template['useFileHash']) {
			return '';
		}
		return hash_file($this->_config->template['useFileHash'], $this->_basepath . $this->path($filename));
	}

	/**
	 * @return string
	 */
	public function getBasepath(): string {
		return $this->_basepath;
	}

}
