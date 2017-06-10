<?php

namespace ricwein\shurl\Template;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Cache;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Filter\Assets;
use ricwein\shurl\Template\Filter\Bindings;
use ricwein\shurl\Template\Filter\Comments;
use ricwein\shurl\Template\Filter\Implode;
use ricwein\shurl\Template\Filter\Includes;

/**
 * simple Template parser with Twig-like syntax
 */
class Template {

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
	public function render($bindings = [], callable $filter = null) {

		$content = $this->make($bindings, $filter);

		echo $content;
		exit(0);
	}

	/**
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return string
	 */
	public function make($bindings = [], callable $filter = null): string{

		$bindings = array_merge((array) $bindings, [
			'template' => ['name' => ucfirst(strtolower(str_replace(['_', '.'], ' ', pathinfo(str_replace($this->config->views['extension'], '', $this->templateFile), PATHINFO_FILENAME))))],
			'config'   => $this->config,
			'name'     => ucfirst(strtolower($this->config->name)),
		]);

		if ($this->cache === null) {
			$content = $this->_load($filter);
			$content = $this->_populate($content, $bindings);
			return $content;
		}

		$templateCache = $this->cache->getItem(
			'template_' .
			$this->template->cachePath($this->templateFile) .
			$this->template->hash($this->templateFile)
		);

		if (null === $content = $templateCache->get()) {

			// load template from file
			$content = $this->_load($filter);

			$templateCache->set($content);
			$templateCache->expiresAfter($this->config->cache['duration']);
			$this->cache->save($templateCache);
		}

		$content = $this->_populate($content, $bindings);
		return $content;
	}

	/**
	 * @param callable|null $filter
	 * @return string
	 */
	protected function _load(callable $filter = null): string{

		// load template from file
		$content = $this->template->read($this->templateFile);

		// run parsers
		$content = (new Includes($this->template))->replace($content);
		$content = (new Comments())->replace($content);

		// run user-defined filters above content
		if ($filter !== null) {
			$content = call_user_func_array($filter, [$content, $this]);
		}

		return $content;
	}

	/**
	 * @param string $content
	 * @param array $bindings
	 * @return string
	 */
	protected function _populate(string $content, array $bindings): string{
		$content = (new Implode())->replace($content, array_merge($bindings, (array) $this->config->views['variables']));
		$content = (new Assets($this->asset, $this->config))->replace($content, array_merge($bindings, (array) $this->config->assets['variables']));
		$content = (new Bindings())->replace($content, array_merge($bindings, (array) $this->config->views['variables']));
		return $content;
	}

}
