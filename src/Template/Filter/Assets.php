<?php

namespace ricwein\shurl\Template\Filter;

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Formatter\Compressed;
use Leafo\ScssPhp\Formatter\Expanded;
use ricwein\shurl\Config\Config;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Engine\Functions;

/**
 * simple Template parser with Twig-like syntax
 */
class Assets extends Functions {

	/**
	 * @var Config
	 */
	protected $_config;

	/**
	 * @param File $file
	 * @param Config $config
	 */
	public function __construct(File $file, Config $config) {
		parent::__construct($file);
		$this->_config = $config;
	}

	/**
	 * @param string $content
	 * @param array $bindings
	 * @param int $currentDepth
	 * @param bool $tag
	 * @return string
	 */
	protected function replace(string $content, array $bindings = [], int $currentDepth = 0, bool $tag = true): string{

		$scss = new Compiler();
		$scss->setImportPaths($this->_file->getBasepath());
		$scss->setVariables($bindings);

		if ($this->_config->development) {
			$scss->setFormatter(new Expanded());
		} else {
			$scss->setFormatter(new Compressed());
		}

		// include other template files
		$content = preg_replace_callback($this->getRegex('asset(.*)'), function (array $match) use ($bindings, $currentDepth, $tag, $scss): string {

			$filename = trim($match[1], '\'" ');

			$scss->addImportPath($this->_file->fullPath($filename, true));

			$filecontent = $this->_file->read($filename, true);
			$filecontent = $scss->compile($filecontent);

			if ($tag) {
				$filecontent = '<style>' . trim($filecontent) . '</style>';
			}

			if ($currentDepth <= self::MAX_DEPTH) {
				return $this->replace($filecontent, $bindings, $currentDepth + 1, false);
			}

			return $filecontent;
		}, $content);

		return $content;
	}

}
