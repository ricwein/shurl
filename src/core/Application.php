<?php

namespace ricwein\shurl\core;

use Hashids\Hashids;
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
	 * @var Logger
	 */
	protected $_logger;

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
		$this->_logger = new Logger('Logger Name');
		$this->_logger->pushHandler(new StreamHandler(
			__DIR__ . '/../../' . ltrim($this->_config->log['path'], '/'),
			$this->_config->log['severity']
		));

		// register as global fallback handler
		ErrorHandler::register($this->_logger);

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

		try {

			if ($slug === null) {
				throw new \UnexpectedValueException('Server Failure, unable to parse URL', 500);
			} elseif ($slug === '') {
				throw new \UnexpectedValueException('Unknown Slug, URL not found', 404);
			}

			$url = $this->getUrl($slug);

			$this->_network->redirect($url);

		} catch (\Throwable $exception) {

			if ($this->_config->development) {
				throw $exception;
			}

			$statusCode = $exception->getCode();
			$this->_logger->addRecord(
				($statusCode > 0 && $statusCode < 500 ? Logger::NOTICE : Logger::ERROR),
				$exception->getMessage(),
				['exception' => $exception]
			);

			$this->_network->setStatusCode($statusCode > 0 ? (int) $statusCode : 500);
		}

	}

	/**
	 * add new URL to index
	 * @param  string $url
	 * @param  string|null $slug
	 * @param  string|null $expires
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function addUrl(string $url, string $slug = null, string $expires = null): URL{

		$url = trim($url);

		$data = [
			'url'  => $url,
			'hash' => hash($this->_config->urls['hash'], $slug, false),
		];

		$query = $this->_pixie->table('urls');
		$query->onDuplicateKeyUpdate($data);
		$urlID = $query->insert($data);

		if ((int) $urlID <= 0) {
			throw new \UnexpectedValueException('database error: unable to insert data', 500);
		} elseif ($slug === null) {
			$hashidEngine = new Hashids($this->_config->urls['salt'], 3, $this->_config->urls['alphabet']);
			$slug         = $hashidEngine->encode($urlID);
		}

		$data = [
			'url_id'  => $urlID,
			'slug'    => trim($slug),
			'expires' => ($expires !== null ? date($this->_config->timestampFormat['database'], strtotime($expires)) : null),
			'enabled' => 1,
		];

		$query = $this->_pixie->table('redirects');
		$query->onDuplicateKeyUpdate($data);
		$query->insert($data);

		return new URL($data['slug'], $url, $this->_config);
	}

	/**
	 * fetch url details from database
	 * @param string $slug
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function getUrl(string $slug): URL{

		$query = $this->_pixie->table('redirects');

		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');

		$query->where('redirects.slug', trim($slug));
		$query->where('redirects.enabled', true);
		$query->where(function ($db) {
			$db->where($db->raw($this->_config->database['prefix'] . 'redirects.expires > NOW()'));
			$db->orWhereNull('redirects.expires');
		});

		$query->select(['redirects.id', 'redirects.slug', 'urls.url']);
		$url = $query->first();

		// slug not found
		if (!$url) {
			throw new \UnexpectedValueException('Unknown Slug, URL not found', 404);
		}

		// handle url tracking
		if ($this->_config->tracking['enabled']) {
			$visit = [
				'redirect_id' => $url->id,
				'visited'     => date($this->_config->timestampFormat['database']),
			];

			// track IP, if either user doesn't send DNT, or we decided to ignore it
			if ($this->_config->tracking['store']['ip'] && (!$this->_config->tracking['respectDNT'] || !$this->_network->hasDNTSet())) {
				$visit['ip'] = inet_pton($this->_network->getIPAddr());
			}

			// also save userAgent, if enabled
			if ($this->_config->tracking['store']['userAgent']) {
				$visit['user_agent'] = $this->_network->getUserAgent();
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
