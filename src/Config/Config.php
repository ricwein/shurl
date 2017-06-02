<?php

namespace ricwein\shurl\Config;

use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

/**
 * provides singleton config object
 */
class Config {

	/**
	 * @var self|null
	 */
	static private $__instance = null;

	/**
	 * default configuration
	 * @var array
	 */
	private $__config = [

		'development'     => false,

		'configFiles'     => [
			'config.yml',
			'database.yml',
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

		'log'             => [
			'path'     => 'logs/error.log',
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
		],

		'redirect'        => [

			// use http status code 301 (permanent) or 302 for redirects?
			// Clients will only tracked first time visiting the url, with permanent active!
			'permanent' => false,

			'allow'     => [

				// allow origin-URL content passthrough, without redirecting
				// this causes all traffic being routed through this server
				'passthrough' => false,

				// redirect to html page, which then redirects per html "refresh" tag
				// this can prevent destination-referrer tracking
				'html'        => true,
			],

			// wait timeout for redirect-methods like html-refresh, which supports this
			'wait'      => 1,
		],

		'cache'           => [
			'enabled'     => true,
			'engine'      => 'auto',
			'duration'    => 3600, // 1h
			'prefix'      => '',
			'passthrough' => true,
			'config'      => [
				'path'     => __DIR__ . '/../../cache/',
				'memcache' => [],
				'redis'    => [],
			],
		],

		'tracking'        => [
			'enabled'    => true,
			'respectDNT' => true,
			'store'      => [
				'ip'        => true,
				'userAgent' => true,
				'referrer'  => true,
			],
		],

		'template'        => [
			'path'        => 'assets/',
			'extension'   => '.html.twig',
			'variables'   => [
				'primaryColor'    => '#28AAE1',
				'accentColor'     => '#333',
				'backgroundColor' => '#EFEFEF',
			],
			'useFileHash' => 'md5', // false or hash method as string
			'route'       => [
				'error'    => 'pages/default/error',
				'welcome'  => 'pages/default/welcome',
				'redirect' => 'pages/default/redirect',
			],
		],
	];

	/**
	 * provide singleton access to configurations
	 * @param bool $createNew init new instance, even if one already exists
	 * @return self
	 */
	public static function getInstance(bool $createNew = false): self {
		if ($createNew === true || static::$__instance === null) {
			static::$__instance = new static($createNew);
		}
		return static::$__instance;
	}

	/**
	 * init new config object
	 * @param bool $createNew init new instance, even if one already exists
	 */
	private function __construct(bool $createNew) {

		$this->_loadConfigFiles();

	}

	/**
	 * load configuration from files
	 * @return self
	 */
	protected function _loadConfigFiles() {
		foreach ($this->__config['configFiles'] as $file) {
			$path = __DIR__ . '/../../config/' . $file;

			if (file_exists($path) && is_readable($path)) {
				$fileConfig     = Yaml::parse(file_get_contents($path));
				$this->__config = array_replace_recursive($this->__config, $fileConfig);
			}
		}

		return $this;
	}

	/**
	 * @param string $name
	 * @return mixed
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
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name) {
		return $this->get($name);
	}

	/**
	 * @param  string $name
	 * @param  mixed $value
	 */
	public function __set(string $name, $value) {
		$this->__config[$name] = $value;
	}

}
