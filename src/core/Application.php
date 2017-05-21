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
	 * @var Network
	 */
	protected $_network;

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

		$this->_network = Network::getInstance($this->_config);

	}

	/**
	 * parse reqeusted shortened URL and redirect to original, if available
	 * @return void
	 * @throws \UnexpectedValueException
	 */
	public function route() {
		$slug = $this->_network->getRoute();

		if ($slug === null) {
			throw new \UnexpectedValueException('Server Failure, unable to parse URL', 500);
		} elseif ($slug === '') {
			throw new \UnexpectedValueException('Unknown Slug, URL not found', 404);
		}

		$url = $this->getUrl($slug);

		$this->_network->redirect($url);
	}

	/**
	 * add new URL to index
	 * @param  string $url
	 * @param  string $slug
	 * @param  string|null $expires
	 * @return URL
	 * @throws \UnexpectedValueException
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
		if (!$query->insert($data)) {
			throw new \UnexpectedValueException('database insertion failed', 500);
		}

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

		// handle url tracking
		if ($this->_config->tracking['enabled']) {
			$visit = [
				'url_id'  => $url->id,
				'visited' => date($this->_config->timestampFormat['database']),
			];

			// track IP, if either user doesn't send DNT, or we decided to ignore it
			if ($this->_config->tracking['trackIP'] && (!$this->_config->tracking['respectDNT'] || !$this->_network->hasDNTSet())) {
				$visit['ip'] = inet_pton($this->_network->getIPAddr());
			}

			// save visitor data
			$query = $this->_pixie->table('visits');
			$query->insert($visit);
		}

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
