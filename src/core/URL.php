<?php

namespace ricwein\shurl\core;
use ricwein\shurl\config\Config;

/**
 * represents a shurl URL object
 */
class URL {

	/**
	 * @var string
	 */
	protected $_originalURL;

	/**
	 * @var string
	 */
	protected $_slug;

	/**
	 * @var string
	 */
	protected $_shortenedURL;

	/**
	 * @param string $slug
	 * @param string $originalURL
	 * @param Config $config
	 */
	public function __construct(string $slug, string $originalURL, Config $config) {
		$this->_slug        = $slug;
		$this->_originalURL = $originalURL;

		$this->_shortenedURL = rtrim($config->rootURL, '/') . '/' . $slug;
	}

	/**
	 * @return string
	 */
	public function getOriginal(): string {
		return $this->_originalURL;
	}

	/**
	 * @return string
	 */
	public function getShortened(): string {
		return $this->_shortenedURL;
	}

	/**
	 * @return string
	 */
	public function getSlug(): string {
		return $this->_slug;
	}

}
