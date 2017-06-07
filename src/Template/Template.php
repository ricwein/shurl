<?php

namespace ricwein\shurl\Template;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Cache;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Filter\Assets;
use ricwein\shurl\Template\Filter\Bindings;
use ricwein\shurl\Template\Filter\Comments;
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
	protected $templateFile;

	/**
	 * @var File
	 */
	protected $asset;

	/**
	 * @var File
	 */
	protected $template;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Cache
	 */
	protected $cache = null;

	/**
	 * @param string $templateFile
	 * @param Config $config
	 * @param Cache|null $cache
	 * @return void
	 * @throws NotFound
	 */
	public function __construct(string $templateFile, Config $config, Cache $cache = null) {
		if (false === $templatePath = realpath(__DIR__ . '/../../' . trim($config->views['path'], '/'))) {
			throw new NotFound('template path not found', 404);
		} elseif (false === $assetPath = realpath(__DIR__ . '/../../' . trim($config->assets['path'], '/'))) {
			throw new NotFound('assets path not found', 404);
		}

		$this->config = $config;
		$this->cache  = $cache;

		$this->template     = new File($templatePath, $this->config);
		$this->asset        = new File($assetPath, $this->config);
		$this->templateFile = $this->template->path($templateFile);
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

		if ($this->cache === null) {
			return $this->_compile($bindings, $filter);
		}

		$templateCache = $this->cache->getItem(
			'template_' .
			$this->template->cachePath($this->templateFile) .
			$this->template->hash($this->templateFile)
		);

		if (null === $content = $templateCache->get()) {

			// load template from file
			$content = $this->_compile($bindings, $filter);

			$templateCache->set($content);
			$templateCache->expiresAfter($this->config->cache['duration']);
			$this->cache->save($templateCache);
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
			'template' => ['name' => ucfirst(strtolower(str_replace(['_', '.'], ' ', pathinfo(str_replace($this->config->views['extension'], '', $this->templateFile), PATHINFO_FILENAME))))],
			'config'   => $this->config,
			'name'     => ucfirst(strtolower($this->config->name)),
		]);

		// load template from file
		$content = $this->template->read($this->templateFile);

		// run parsers
		$content = (new Includes($this->template))->replace($content);
		$content = (new Comments())->replace($content);
		$content = (new Bindings())->replace($content, array_merge($bindings, (array) $this->config->views['variables']));
		$content = (new Assets($this->asset, $this->config))->replace($content, array_merge($bindings, (array) $this->config->assets['variables']));

		// run user-defined filters above content
		if ($filter !== null) {
			$content = call_user_func_array($filter, [$content, $this]);
		}

		return $content;
	}

}
