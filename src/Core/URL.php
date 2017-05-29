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
	 * @var array
	 */
	protected $_additionals;

	/**
	 * @param int $redirectID
	 * @param string $slug
	 * @param string $originalURL
	 * @param Config $config
	 * @param array $additionals
	 */
	public function __construct(int $redirectID, string $slug, string $originalURL, Config $config, array $additionals = []) {
		$this->_redirectID  = $redirectID;
		$this->_slug        = $slug;
		$this->_originalURL = $originalURL;
		$this->_additionals = $additionals;

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

	/**
	 * @param string $name
	 * @return mixed|null
	 */
	public function getAdditional(string $name) {
		if (array_key_exists($name, $this->_additionals)) {
			return $this->_additionals[$name];
		} else {
			return null;
		}
	}

	/**
	 * @return string
	 */
	public function getHash(): string {
		return hash(Config::getInstance()->urls['hash'], $this->_originalURL, false);
	}

}
