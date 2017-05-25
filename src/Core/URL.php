<?php

namespace ricwein\shurl\Core;

use ricwein\shurl\Config\Config;

/**
 * represents a shurl URL object
 */
class URL {

	/**
	 * @var int
	 */
	protected $_redirectID;

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
	 * @param int $redirectID
	 * @param string $slug
	 * @param string $originalURL
	 * @param Config $config
	 */
	public function __construct(int $redirectID, string $slug, string $originalURL, Config $config) {
		$this->_redirectID  = $redirectID;
		$this->_slug        = $slug;
		$this->_originalURL = $originalURL;

		$this->_shortenedURL = rtrim($config->rootURL, '/') . '/' . $slug;
	}

	/**
	 * @return int
	 */
	public function getRedirectID(): int {
		return $this->_redirectID;
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
