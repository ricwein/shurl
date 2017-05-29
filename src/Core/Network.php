<?php

namespace ricwein\shurl\Core;
use ricwein\shurl\Config\Config;

/**
 * provides HTTP Networking methods
 */
class Network {

	/**
	 * @var self|null
	 */
	private static $__instance = null;

	/**
	 * provide singleton access for networking methods
	 * @return self
	 */
	public static function getInstance(): self {
		if (static::$__instance === null) {
			static::$__instance = new static();
		}
		return static::$__instance;
	}

	/**
	 * provides public access to private and protected methods as non-static calls
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call(string $method, array $args) {
		return call_user_func_array([$this, $method], $args);
	}

	/**
	 * provides public static access to private and protected methods implicitly calling self::instance()
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public static function __callStatic(string $method, array $args) {
		return call_user_func_array([static::getInstance(), $method], $args);
	}

	/**
	 * send redirect header
	 *
	 * this ends the current code execution!
	 * @param URL $url
	 * @param bool $permanent
	 * @return void
	 */
	protected function redirect(URL $url, bool $permanent = false) {

		if ($permanent) {

			$this
				->setStatusCode(301)
				->setHeader('Cache-Control', 'max-age=3600');

		} else {

			$this
				->setStatusCode(302)
				->setHeader('Pragma', 'no-cache')
				->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
				->setHeader('Expires', '0');

		}

		$this->setHeader('Location', $url->getOriginal());
		exit(0);
	}

	/**
	 * allows server-side originURL fetching
	 * and direct rendering to client, without redirection
	 * @param URL $url
	 * @param Cache|null $cache
	 * @return void
	 */
	protected function passthrough(URL $url, Cache $cache = null) {

		if ($cache === null) {
			$headers = get_headers($url->getOriginal());
			$this->setHeaders($headers);
			readfile($url->getOriginal());
			exit(0);
		}

		$contentCache = $cache->getItem('url_' . $url->getHash());

		if (null === $ressource = $contentCache->get()) {
			$ressource = [
				'headers' => get_headers($url->getOriginal()),
				'content' => file_get_contents($url->getOriginal()),
			];

			$contentCache->set($ressource);
			$contentCache->expiresAfter(Config::getInstance()->cache['duration']);
			$cache->save($contentCache);
		}

		$this->setHeaders($ressource['headers']);
		echo $ressource['content'];
		exit(0);
	}

	/**
	 * fetch current route from URL
	 * @return string|null
	 */
	protected function getRoute() {

		if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
			$path = $_SERVER['REQUEST_URI'];

			if (isset($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['SCRIPT_NAME'])) {

				// only replace first occurence of script-name in path
				$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
				$scriptName = basename($_SERVER['SCRIPT_NAME']);
				if (substr($path, 0, strlen($scriptPath)) === $scriptPath) {
					$path = substr($path, strlen($scriptPath));
				}
				if (substr($path, 0, strlen($scriptName)) === $scriptName) {
					$path = substr($path, strlen($scriptName));
				}

			} elseif (isset($_SERVER['DOCUMENT_URI']) && !empty($_SERVER['DOCUMENT_URI'])) {
				$path = str_replace(dirname($_SERVER['DOCUMENT_URI']), '', $path);
			}

			return trim($path, '/');
		} elseif (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
			return trim($_SERVER['PATH_INFO'], '/');
		}

		return null;
	}

	/**
	 * check for DNT header
	 * @return bool
	 */
	protected function hasDNTSet(): bool {

		/**
		 * @var bool
		 */
		static $hasDNT = null;
		if ($hasDNT !== null) {
			return $hasDNT;
		}

		$hasDNT = (isset($_SERVER['HTTP_DNT']) && (int) $_SERVER['HTTP_DNT'] === 1);

		return $hasDNT;
	}

	/**
	 * fetch client IP address
	 * @return string|null
	 */
	protected function getIPAddr() {

		foreach ([
			'REMOTE_ADDR',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'SERVER_ADDR',
		] as $header) {

			if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
				return trim($_SERVER[$header]);
			}

		}

		return null;
	}

	/**
	 * @return string|null
	 */
	protected function getUserAgent() {
		return $this->get('HTTP_USER_AGENT');
	}

	/**
	 * @return bool
	 */
	protected function isSecured(): bool {
		return isset($_SERVER['HTTPS']);
	}

	/**
	 * @param string $default
	 * @return string
	 */
	protected function getBaseURL(string $default): string{
		$schema = ($this->isSecured() ? 'https' : 'http');

		if (false === $host = $this->get('HTTP_HOST', false)) {
			return $default;
		}

		if (false === $path = $this->get('REQUEST_URI', false)) {
			return $default;
		}

		$host = rtrim($host, '/');
		$path = trim(str_replace($this->getRoute(), '', $path), '/');

		return $schema . '://' . rtrim($host . '/' . $path, '/');
	}

	/**
	 * @return string|null
	 */
	protected function getReferrer() {
		return $this->get('HTTP_REFERER', null);
	}

	/**
	 * @param  string $name
	 * @param  mixed $default
	 * @return mixed
	 */
	protected function get(string $name, $default = null) {
		if (isset($_SERVER[$name]) && !empty($_SERVER[$name])) {
			return trim($_SERVER[$name]);
		}
		return $default;
	}

	/**
	 * @param string $name
	 * @param string $message
	 * @return self
	 */
	protected function setHeader(string $name, string $message): self{
		header($name . ': ' . $message);
		return $this;
	}

	/**
	 * @param array $headers
	 * @return self
	 */
	protected function setHeaders(array $headers): self {
		foreach ($headers as $key => $value) {
			if (is_int($key)) {
				header($value);
			} else {
				$this->setHeader($key, $value);
			}
		}
		return $this;
	}

	/**
	 * @param int $code
	 * @return self
	 */
	protected function setStatusCode(int $code): self{
		http_response_code($code);
		return $this;
	}

}
