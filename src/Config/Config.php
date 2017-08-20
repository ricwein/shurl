<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Config;

use Monolog\Logger;

use ricwein\shurl\Core\Helper;

use Symfony\Component\Yaml\Yaml;

/**
 * provides singleton config object
 */
class Config {

    /**
     * @var self|null
     */
    private static $__instance = null;

    /**
     * default configuration
     * @var array
     */
    private $__config = [
        'name'            => 'shurl',
        'development'     => false,

        'imports'         => [
            ['resource' => 'config.yml'],
        ],

        'database'        => [
            'database'  => 'shurl',
            'username'  => 'shurl',
            'password'  => '',
            'prefix'    => '',

            'host'      => '127.0.0.1',
            'driver'    => 'mysql',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'paths'           => [
            'log'    => '/logs',
            'cache'  => '/cache',
            'view'   => '/views',
            'asset'  => '/assets',
            'config' => '/config',
        ],

        'log'             => [
            'path'     => '@log/error.log',
            'severity' => Logger::WARNING, // 300
        ],

        'rootURL'         => 'localhost',
        'timestampFormat' => [
            'database' => 'Y-m-d H:i:s',
        ],

        'urls'            => [
            'alphabet' => 'bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ0123456789',
            'hash'     => 'sha256',
            'salt'     => '',

            // reserved paths (slugs)
            'reserved' => [
                'assets', 'images', // frontend ressources
                'api', 'preview', // shurl features
            ],
        ],

        'redirect'        => [

            // use http status code 301 (permanent) or 302 for redirects?
            // Clients will only tracked first time visiting the url, with permanent active!
            'permanent'        => false,

            // wait timeout for redirect-methods like html-refresh, which supports this
            'wait'             => 1,

            'cachePassthrough' => true,
        ],

        'cache'           => [
            'enabled'  => true,
            'engine'   => 'auto', // phpFastCache driver
            'fallback' => 'file',
            'duration' => 3600, // default duration: 1h
            'prefix'   => null,
            'path'     => '@cache/cache/', // path for filecache
            'memcache' => [], // memcache configuration, see phpFastCache
            'redis'    => [], // redis configuration, see phpFastCache
        ],

        'tracking'        => [
            'enabled'     => true,
            'respectDNT'  => true,
            'skipOnError' => true, // skip tracking if writing to db fails
            'store'       => [
                'ip'        => true,
                'userAgent' => true,
                'referrer'  => true,
            ],
        ],

        'views'           => [
            'path'           => '@view',
            'extension'      => '.html.twig',
            'variables'      => [],
            'useFileHash'    => 'md5', // false or hash method as string
            'removeComments' => true,
            'route'          => [
                'error'    => 'pages/error',
                'welcome'  => 'pages/welcome',
                'redirect' => 'pages/redirect',
                'preview'  => 'pages/preview',
            ],
            'expires'        => 604800, // 1w
        ],

        'assets'          => [
            'path'      => '@asset',
            'variables' => [
                'color-accent' => '#28aae1',
            ],
            'inline'    => false,
            'expires'   => 604800, // 1w
        ],
    ];

    /**
     * @var array|null
     */
    private $paths = null;

    /**
     * provide singleton access to configurations
     * @param  array|null $override
     * @return self
     */
    public static function getInstance(array $override = null): self {
        if (static::$__instance === null) {
            static::$__instance = new static();
        }
        if ($override !== null) {
            static::$__instance->set($override);
        }
        return static::$__instance;
    }

    /**
     * init new config object
     */
    private function __construct() {
        $this->loadConfigFiles($this->__config['imports']);
        $this->resolvePaths();
    }

    /**
     * load configuration from files
     * @param  array $importList
     * @param  array $loaded
     * @return self
     */
    protected function loadConfigFiles(array $importList, array $loaded = []) {
        foreach ($importList as $import) {
            if (!is_array($import) || !isset($import['resource'])) {
                continue;
            }

            $path = $this->resolvePath($import['resource'], __DIR__ . '/../../config/');

            if ($path !== false && !in_array($path, $loaded, true) && file_exists($path) && is_readable($path)) {
                $fileConfig     = Yaml::parse(file_get_contents($path));
                $this->__config = array_replace_recursive($this->__config, $fileConfig);

                if (isset($fileConfig['paths'])) {
                    $this->paths = null;
                }

                if (isset($fileConfig['imports'])) {
                    $this->loadConfigFiles($fileConfig['imports'], $loaded);
                }

                $loaded[] = $path;
            }
        }

        return $this;
    }

    /**
     * @param  string|null $relativePath
     * @return void
     */
    protected function resolvePaths(string $relativePath = null) {
        $this->__config = Helper::array_map_recursive(function ($item) {
            return is_string($item) ? strtr($item, $this->getPaths()) : $item;
        }, $this->__config);
    }

    /**
     * @param  string      $filepath
     * @param  string|null $relativePath
     * @return string|null
     */
    protected function resolvePath(string $filepath, string $relativePath = null) {
        $filepath = strtr($filepath, $this->getPaths());

        if (strpos($filepath, '/') === 0 && false !== $resolved = realpath($filepath)) {
            return $resolved;
        } elseif (strpos($filepath, '/') === 0) {
            return realpath(__DIR__ . '/../../' . ltrim($filepath, '/'));
        } elseif ($relativePath !== null) {
            return realpath($relativePath . ltrim($filepath, '/'));
        }

        return $filepath;
    }

    /**
     * @return array
     */
    protected function getPaths(): array {
        if ($this->paths === null) {
            $this->paths = [];

            foreach ($this->get('paths') as $key => $path) {
                $this->paths['@' . $key] = realpath(__DIR__ . '/../../' . ltrim($path, '/')) . '/';
            }
        }

        return $this->paths;
    }

    /**
     * @param  string|null $name
     * @return mixed|null
     */
    public function get(string $name = null) {
        if ($name === null) {
            return $this->__config;
        } elseif (array_key_exists($name, $this->__config)) {
            return $this->__config[$name];
        }
        return null;
    }

    /**
     * @param  array $config
     * @return self
     */
    public function set(array $config) {
        $this->__config = array_replace_recursive($this->__config, $config);
        return $this;
    }

    /**
     * @param  string     $name
     * @return mixed|null
     */
    public function __get(string $name) {
        return $this->get($name);
    }

    /**
     * @param  string $name
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $name, $value) {
        $this->__config[$name] = $value;
    }

    /**
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool {
        return array_key_exists($name, $this->__config);
    }
}
