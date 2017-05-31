<?php

namespace ricwein\shurl\Template;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Cache;
use ricwein\shurl\Core\Network;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Filter\Assets;
use ricwein\shurl\Template\Filter\Bindings;
use ricwein\shurl\Template\Filter\Includes;

/**
 * simple Template parser with Twig-like syntax
 */
class Template {

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
	 * @var File
	 */
	protected $_file;

	/**
	 * @param string $templateFile
	 * @param Config $config
	 * @param Network $network
	 * @param Cache|null $cache
	 * @return void
	 * @throws NotFound
	 */
	public function __construct(string $templateFile, Config $config, Network $network, Cache $cache = null) {
		if (false === $templatePath = realpath(__DIR__ . '/../../' . trim($config->template['path'], '/'))) {
			throw new NotFound('template path not found', 404);
		}

		$this->_config  = $config;
		$this->_network = $network;
		$this->_cache   = $cache;

		$this->_file         = new File($templatePath, $this->_config);
		$this->_templateFile = $this->_file->path($templateFile);
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

		if ($this->_cache === null) {
			return $this->_compile($bindings, $filter);
		}

		$templateCache = $this->_cache->getItem(
			'template_' .
			$this->_file->cachePath($this->_templateFile) .
			$this->_file->hash($this->_templateFile)
		);

		if (null === $content = $templateCache->get()) {

			// load template from file
			$content = $this->_compile($bindings, $filter);

			$templateCache->set($content);
			$templateCache->expiresAfter($this->_config->cache['duration']);
			$this->_cache->save($templateCache);
		}

		return $content;
	}

	/**
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return string
	 */
	protected function _compile($bindings = null, callable $filter = null): string{

		$bindings = array_merge((array) $bindings, [
			'base_url' => $this->_network->getBaseURL($this->_config->rootURL),
			'name'     => ucfirst(strtolower(str_replace(['_', '.'], ' ', pathinfo(str_replace($this->_config->template['extension'], '', $this->_templateFile), PATHINFO_FILENAME)))),
		], (array) $this->_config->template['variables']);

		// load template from file
		$content = $this->_file->read($this->_templateFile);

		// run parsers
		$content = (new Includes($this->_file))->replace($content);
		$content = (new Bindings())->replace($content, $bindings);
		$content = (new Assets($this->_file, $this->_config))->replace($content, $bindings);

		// run user-defined filters above content
		if ($filter !== null) {
			$content = call_user_func_array($filter, [$content, $this]);
		}

		return $content;
	}

}
