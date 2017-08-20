<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Core;

use phpFastCache\CacheManager;
use phpFastCache\Core\Item\ExtendedCacheItemInterface;
use phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface;

use Psr\Cache\CacheItemInterface;

/**
 * PSR 6 Cache Wrapper with prefix support
 *
 * @method bool clear()
 * @method bool save(CacheItemInterface $item)
 * @method CacheItemInterface saveDeferred(CacheItemInterface $item)
 * @method bool commit()
 * @method ExtendedCacheItemInterface[] getItemsByTag(string $tagName)
 * @method ExtendedCacheItemInterface[] getItemsByTags(array $tagNames)
 * @method ExtendedCacheItemInterface[] getItemsByTagsAll(array $tagNames)
 * @method bool deleteItemsByTag(string $tagName)
 * @method bool deleteItemsByTags(array $tagNames)
 * @method bool deleteItemsByTagsAll(array $tagNames)
 */
class Cache {

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
     * @param array  $config
     */
    public function __construct(string $engine, array $config) {
        if (isset($config['prefix']) && $config['prefix'] !== null) {
            $this->setPrefix($config['prefix']);
        }

        // load caching-adapter
        try {
            if (strtolower($engine) === 'auto') {
                $this->_cache = static::_loadDynamicCache($config);
            } else {
                $this->_cache = CacheManager::getInstance($engine, $config);
            }
        } catch (\Exception $e) {
            $this->_cache = CacheManager::getInstance($config['fallback'], $config);
        }
    }

    /**
     * @param  string $prefix
     * @return self
     */
    public function setPrefix(string $prefix = ''): self {
        $this->_prefix = trim(rtrim((string) $prefix, '._-')) . '.';
        return $this;
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
     * @param  array                          $config
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
        }
        return CacheManager::getInstance('files', $config);
    }

    /**
     * @param  string $key
     * @return string
     */
    protected function prefixString(string $key): string {
        return $this->_prefix . str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':'],
            ['|', '|', '|', '|', '.', '.', '-', '_'],
            $key
        );
    }

    /**
     * @param  array $keys
     * @param  bool  $recursive
     * @return array
     */
    protected function prefixArray(array $keys, bool $recursive = false): array {
        foreach ($keys as &$key) {
            if (is_string($key)) {
                $key = $this->prefixString($key);
            } elseif ($recursive && is_array($key)) {
                $key = $this->prefixArray($key, $recursive);
            }
        }
        return $keys;
    }

    /**
     * @return ExtendedCacheItemPoolInterface
     */
    public function getDriver(): ExtendedCacheItemPoolInterface {
        return $this->_cache;
    }

    /**
     * @param string $key
     *
     * @return ExtendedCacheItemInterface
     */
    public function getItem(string $key): ExtendedCacheItemInterface {
        return $this->_cache->getItem($this->prefixString($key));
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasItem(string $key): bool {
        return $this->_cache->hasItem($this->prefixString($key));
    }

    /**
     * @param string[] $keys
     *
     * @return CacheItemInterface[]
     */
    public function getItems(array $keys = []): array {
        return $this->_cache->getItems($this->prefixArray($keys));
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function deleteItem(string $key): bool {
        return $this->_cache->deleteItem($this->prefixString($key));
    }

    /**
     * @param string[] $keys
     *
     * @return bool
     */
    public function deleteItems(array $keys): bool {
        return $this->_cache->deleteItems($this->prefixArray($keys));
    }

    /**
     * @param CacheItemInterface $item
     *
     * @return self
     */
    public function setItem(CacheItemInterface $item) {
        $this->_cache->setItem($item);
        return $this;
    }

    /**
     * @param  string $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments) {

            // execute method-call at cache-driver (itemPool)
        if (method_exists($this->_cache, $name)) {
            return @call_user_func_array([$this->_cache, $name], $arguments);
        }

        // method not found
        throw new \RuntimeException(sprintf(
            'Call to undefined Cache method %s::%s()',
            $this->getDriver()->getDriverName(),
            $name
        ), 500);
    }
}
