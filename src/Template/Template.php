<?php

namespace ricwein\shurl\Template;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Cache;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Processor;

/**
 * simple Template parser with Twig-like syntax
 */
class Template {

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
	 * @param Config $config
	 * @param Cache|null $cache
	 * @return void
	 * @throws NotFound
	 */
	public function __construct(Config $config, Cache $cache = null) {
		if (false === $templatePath = realpath(__DIR__ . '/../../' . trim($config->views['path'], '/'))) {
			throw new NotFound('template path not found', 404);
		} elseif (false === $assetPath = realpath(__DIR__ . '/../../' . trim($config->assets['path'], '/'))) {
			throw new NotFound('assets path not found', 404);
		}

		$this->config = $config;
		$this->cache  = $cache;

		$this->template = new File($templatePath, $this->config);
		$this->asset    = new File($assetPath, $this->config);
	}

	/**
	 * @param string $templateFile
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return string
	 */
	public function make(string $templateFile, $bindings = [], callable $filter = null): string{

		$templateFile = $this->template->path($templateFile);
		$bindings     = (array) $bindings;

		if ($this->cache === null) {
			$content = $this->_load($templateFile, $filter);
			$content = $this->_populate($content, $bindings);
			return $content;
		}

		$templateCache = $this->cache->getItem(
			'template_' .
			$this->template->cachePath($templateFile) .
			$this->template->hash($templateFile)
		);

		if (null === $content = $templateCache->get()) {

			// load template from file
			$content = $this->_load($templateFile, $filter);

			$templateCache->set($content);
			$templateCache->expiresAfter($this->config->views['expires']);
			$this->cache->save($templateCache);
		}

		$content = $this->_populate($content, $bindings);
		return $content;
	}

	/**
	 * @param string $templateFile
	 * @param callable|null $filter
	 * @return string
	 */
	protected function _load(string $templateFile, callable $filter = null): string{

		// load template from file
		$content = $this->template->read($templateFile);

		// run parsers
		$content = (new Processor\Includes($this->template))->replace($content);
		$content = (new Processor\Comments())->replace($content, !$this->config->views['removeComments']);

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
		$content = (new Processor\Implode())->replace($content, array_replace_recursive($bindings, (array) $this->config->views['variables']));
		$content = (new Processor\Assets($this->asset, $this->config))->replace($content, array_replace_recursive($bindings, (array) $this->config->assets['variables']));
		$content = (new Processor\Bindings())->replace($content, array_replace_recursive($bindings, (array) $this->config->views['variables']));
		$content = (new Processor\Minify($this->config))->replace($content);
		return $content;
	}

}
