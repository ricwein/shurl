<?php

namespace ricwein\shurl\Core;

use phpFastCache\CacheManager;
use phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface;

/**
 * PSR 6 Cache Wrapper
 */
class Cache {

	/**
	 * @var array
	 */
	private static $__instance = [];

	/**
	 * @var ExtendedCacheItemPoolInterface
	 */
	protected $_cache = null;

	/**
	 * @var string
	 */
	protected $_prefix = '';

	/**
	 * @param string $engine cache-type
	 * @param array $config
	 */
	public function __construct(string $engine = 'auto', array $config) {
		if (isset($config['prefix'])) {
			$this->setPrefix($config['prefix']);
		}

		// load caching-adapter
		if (!$config['enabled']) {
			$this->_cache = CacheManager::getInstance('Phparray', $config);
		} elseif (strtolower($engine) === 'auto') {
			$this->_cache = static::_loadDynamicCache($config);
		} else {
			$this->_cache = CacheManager::getInstance($engine, $config);
		}
	}

	/**
	 * @param string $prefix
	 * @return self
	 */
	public function setPrefix(string $prefix = ''): self{
		$this->_prefix = trim(rtrim((string) $prefix, '._-')) . '.';
		return $this;
	}

	/**
	 * @param string $engine
	 * @param array $config
	 * @return self
	 */
	public static function instance(string $engine = 'auto', array $config = null): self{
		$name = strtolower($engine);

		if (!isset(static::$__instance[$name])) {
			static::$__instance[$name] = new static($engine, $config);
		}

		return static::$__instance[$name];
	}

	/**
	 * clears object variables
	 */
	public function __destruct() {
		unset($this->_cache);
	}

	/**
	 * use dynamic cache-adapter
	 * apc(u) > (p)redis > memcache(d) > file
	 * @param array $config
	 * @return ExtendedCacheItemPoolInterface
	 */
	protected static function _loadDynamicCache(array $config): ExtendedCacheItemPoolInterface {
		if (extension_loaded('apcu')) {
			return CacheManager::getInstance('apcu', $config);
		} elseif (ini_get('apc.enabled')) {
			return CacheManager::getInstance('apc', $config);
		} elseif (extension_loaded('redis')) {
			return CacheManager::getInstance('redis', $config);
		} elseif (class_exists('Predis\Client')) {
			return CacheManager::getInstance('predis', $config);
		} elseif (extension_loaded('memcached')) {
			return CacheManager::getInstance('memcached', $config);
		} elseif (extension_loaded('memcache')) {
			return CacheManager::getInstance('memcache', $config);
		} else {
			return CacheManager::getInstance('files', $config);
		}
	}

	/**
	 * @return ExtendedCacheItemPoolInterface
	 */
	public function getDriver(): ExtendedCacheItemPoolInterface {
		return $this->_cache;
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments) {

		// prefix keys and tags (also arrays)
		if (isset($arguments[0]) && is_string($arguments[0])) {
			$arguments[0] = $this->_prefix . $arguments[0];
		} elseif (isset($arguments[0]) && is_array($arguments[0])) {
			$arguments[0] = array_map(function ($name) {
				return $this->_prefix . $name;
			}, $arguments[0]);
		}

		// execute method-call at cache-driver (itemPool)
		if (method_exists($this->_cache, $name)) {
			return @call_user_func_array([$this->_cache, $name], $arguments);
		}

		// method not found
		throw new \RuntimeException(sprintf(
			'Call to undefined Cache method %s::%s()',
			get_class($this->getDriver()),
			$name
		), 500);
	}
}
