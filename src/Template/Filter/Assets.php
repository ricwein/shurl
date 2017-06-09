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
	protected $config;

	/**
	 * @param File $file
	 * @param Config $config
	 */
	public function __construct(File $file, Config $config) {
		parent::__construct($file);
		$this->config = $config;
	}

	/**
	 * replace scss assets with either inline css or link
	 *
	 * [asset 'name.scss' [| inline|link]]
	 * @param string $content
	 * @param array $bindings
	 * @return string
	 */
	protected function replace(string $content, array $bindings = []): string{

		// include other template files
		$content = preg_replace_callback($this->getRegex('asset(.*)(\|.*)?'), function (array $match) use ($bindings): string {

			$filename = trim($match[1]);

			if (false !== $pos = strpos($filename, '|')) {
				$method   = trim(substr($filename, $pos + 1, strlen($filename)));
				$inline   = ($method === 'inline' ? true : ($method === 'link' ? false : $this->config->assets['inline']));
				$filename = trim((substr($filename, 0, $pos)), '\'" ');
			} else {
				$filename = trim($filename, '\'" ');
				$inline   = $this->config->assets['inline'];
			}

			if ($inline) {
				$filecontent = $this->parse($filename, $bindings);
				$filecontent = '<style>' . trim($filecontent) . '</style>';
				return $filecontent;
			}

			$filename = pathinfo($filename, PATHINFO_FILENAME) . '.css';
			return '<link crossorigin="anonymous" href="/assets/css/' . $filename . '" media="all" rel="stylesheet" />';

		}, $content);

		return $content;
	}

	/**
	 * @param string $filename
	 * @param array $bindings
	 * @return string
	 */
	public function parse(string $filename, array $bindings = []): string {

		/**
		 * @var Compiler
		 */
		static $compiler;

		$bindings = array_filter($bindings, function ($entry): bool {
			return is_scalar($entry) || (is_object($entry) && method_exists($entry, '__toString'));
		});

		if ($compiler === null) {
			$compiler = new Compiler();
			$compiler->setImportPaths($this->_file->getBasepath());
			if ($this->config->development) {
				$compiler->setFormatter(new Expanded());
			} else {
				$compiler->setFormatter(new Compressed());
			}
		}

		$compiler->setVariables($bindings);
		$compiler->addImportPath($this->_file->fullPath($filename, true));

		$filecontent = $this->_file->read($filename, true);
		return $compiler->compile($filecontent);
	}

}
