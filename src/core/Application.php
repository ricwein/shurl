<?php

namespace ricwein\shurl\core;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;
use ricwein\shurl\config\Config;

/**
 * shurl core class
 */
class Application {

	/**
	 * @var Config
	 */
	protected $_config;

	/**
	 * @var QueryBuilderHandler
	 */
	protected $_pixie;

	/**
	 * init new shurl Core
	 * @param Config|null $config
	 */
	public function __construct(Config $config = null) {

		// allocate config instance
		if ($config === null) {
			$this->_config = Config::getInstance();
		} else {
			$this->_config = $config;
		}

		// init new monolog logger
		$logger = new Logger('Logger Name');
		$logger->pushHandler(new StreamHandler(
			__DIR__ . '/../../' . ltrim($this->_config->log['path'], '/'),
			$this->_config->log['severity']
		));

		// register as global fallback handler
		ErrorHandler::register($logger);

		// init new database connection and Pixie querybuilder
		$this->_pixie = new QueryBuilderHandler(new Connection(
			$this->_config->database['driver'],
			$this->_config->database
		));
	}

	/**
	 * parse reqeusted shortened URL and redirect to original, if available
	 * @return void
	 * @throws \UnexpectedValueException
	 */
	public function route() {
		$slug = $this->_getRoute();

		if ($slug === null) {
			throw new \UnexpectedValueException('Server Failure, unable to parse URL', 500);
		} elseif ($slug === '') {
			throw new \UnexpectedValueException('Unknown Slug, URL not found', 404);
		}

		$url = $this->getUrl($slug);

		$this->_redirect($url);
	}

	/**
	 * send redirect header
	 *
	 * this ends the current code execution!
	 * @param URL $url
	 * @return void
	 */
	protected function _redirect(URL $url) {

		if ($this->_config->cache['clientRedirectCaching'] && !$this->_config->development) {
			http_response_code(301);
			header('Cache-Control: max-age=3600');
		} else {
			http_response_code(302);
			header('Pragma: no-cache');
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('Expires: 0');
		}

		header('Location: ' . $url->getOriginal());
		exit(0);
	}

	/**
	 * fetch current route from URL
	 * @return string|null
	 */
	protected function _getRoute() {

		if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
			$path = $_SERVER['REQUEST_URI'];

			if (isset($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['SCRIPT_NAME'])) {

				// only replace first occurence of script-name in path
				$scriptName = dirname($_SERVER['SCRIPT_NAME']);
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
	 * add new URL to index
	 * @param  string $url
	 * @param  string $slug
	 * @param  string|null $expires
	 * @return URL
	 */
	public function addUrl(string $url, string $slug, string $expires = null): URL{
		$data = [
			'url'     => trim($url),
			'slug'    => trim($slug),
			'expires' => ($expires !== null ? date($this->_config->timestampFormat['database'], strtotime($expires)) : null),
			'enabled' => 1,
		];

		$query = $this->_pixie->table('redirects');
		$query->onDuplicateKeyUpdate($data);
		$query->insert($data);

		return new URL($data['slug'], $data['url'], $this->_config);
	}

	/**
	 * fetch url details from database
	 * @param string $slug
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function getUrl(string $slug): URL{

		$query = $this->_pixie->table('redirects');
		$query->where('slug', '=', trim($slug));
		$url = $query->first();

		// slug not found
		if (!$url) {
			throw new \UnexpectedValueException('Unknown Slug, URL not found', 404);
		}

		// increment url hit counter
		$query = $this->_pixie->table('redirects');
		$query->where('slug', '=', $slug);
		$query->update([
			'hits' => $this->_pixie->raw('hits + 1'),
		]);

		return new URL($url->slug, $url->url, $this->_config);
	}

	/**
	 * get internal Pixie Database QueryBuilder Object
	 * @return QueryBuilderHandler
	 */
	public function getDB(): QueryBuilderHandler {
		return $this->_pixie;
	}

}
