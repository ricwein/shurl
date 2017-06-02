<?php

namespace ricwein\shurl\Core;

use Hashids\Hashids;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;
use ricwein\shurl\Config\Config;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Template;

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
	 * @var Cache|null
	 */
	protected $_cache = null;

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

		// init new network class
		$this->_network = Network::getInstance();

		if ($this->_config->cache['enabled']) {
			$this->_cache = new Cache($this->_config->cache['engine'], $this->_config->cache['config']);
			$this->_cache->setPrefix($this->_config->cache['prefix']);
		}

		// init new monolog logger
		$this->_logger = new Logger('Logger Name');
		$this->_logger->pushHandler(new StreamHandler(
			__DIR__ . '/../../' . ltrim($this->_config->log['path'], '/'),
			$this->_config->log['severity']
		));

		// register as global fallback handler
		if (!$this->_config->development) {
			ErrorHandler::register($this->_logger);
		}

		try {

			// init new database connection and Pixie querybuilder
			$this->_pixie = new QueryBuilderHandler(new Connection(
				$this->_config->database['driver'],
				$this->_config->database
			));

		} catch (\Throwable $exception) {

			throw new \Exception('Unable to connect to Database', 1, $exception);

		}
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
				$this->viewWelcome();
			}

			$url = $this->getUrl($slug);

			if ($this->_config->redirect['allow']['passthrough'] && $url->getAdditional('passthrough')) {
				$this->_network->passthrough($this->_config, $url, ($this->_config->cache['passthrough'] ? $this->_cache : null));
			} elseif ($this->_config->redirect['allow']['html'] && $url->getAdditional('dereferrer')) {
				(new Template('redirect', $this->_config, $this->_network, $this->_cache))->render([
					'url'  => $url->getOriginal(),
					'wait' => (int) $this->_config->redirect['wait'],
				]);
			} else {
				$this->_network->redirect($this->_config, $url, $this->_config->redirect['permanent'] && !$this->_config->development);
			}

		} catch (\Throwable $exception) {

			$this->_logger->addRecord(
				($exception->getCode() > 0 && $exception->getCode() < 500 ? Logger::NOTICE : Logger::ERROR),
				$exception->getMessage(),
				['exception' => $exception]
			);

			$this->viewError($exception);
		}
	}

	/**
	 * @param \Throwable $throwable
	 * @return void
	 */
	public function viewError(\Throwable $throwable) {
		$template = new Template('error', $this->_config, $this->_network, $this->_cache);

		$this->_network->setStatusCode($throwable->getCode() > 0 ? (int) $throwable->getCode() : 500);

		$template->render([
			'type'    => (new \ReflectionClass($throwable))->getShortName(),
			'code'    => $throwable->getCode(),
			'message' => $throwable->getMessage(),
		]);
	}

	/**
	 * @return void
	 */
	public function viewWelcome() {
		$template = new Template('welcome', $this->_config, $this->_network, $this->_cache);

		if ($this->_cache === null) {
			$template->render(['count' => $this->_getEntryCount()]);
		}

		$countCache = $this->_cache->getItem('count');

		if (null === $count = $countCache->get()) {
			$count = $this->_getEntryCount();
			$countCache->set($count);
			$countCache->expiresAfter(60);
			$this->_cache->save($countCache);
		}

		$template->render(['count' => $count]);
	}

	/**
	 * @return int
	 */
	protected function _getEntryCount(): int{
		$query = $this->_pixie->table('redirects');
		$query->select([$query->raw('COUNT(*) as count')]);

		// only select currently enabled entries
		$query->where('enabled', '=', true);
		$query->where(function ($db) {
			$db->where($db->raw('valid_to > NOW()'));
			$db->orWhereNull('valid_to');
		});
		$query->where(function ($db) {
			$db->where($db->raw('valid_from < NOW()'));
			$db->orWhereNull('valid_from');
		});
		return $query->first()->count;
	}

	/**
	 * add new URL to index
	 * @param  string $url
	 * @param  string|null $slug
	 * @param  string|null $starts
	 * @param  string|null $expires
	 * @param  bool $passthrough
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function addUrl(string $url, string $slug = null, string $starts = null, string $expires = null, bool $passthrough = false): URL{

		$url = trim($url);

		$data = [
			'url'  => $url,
			'hash' => hash($this->_config->urls['hash'], $url, false),
		];

		$query = $this->_pixie->table('urls');
		$query->onDuplicateKeyUpdate($data);

		// workaround for pixie not returning LAST_INSERT_ID(), if onDuplicate matches
		if (0 >= $urlID = $query->insert($data)) {
			$urlTemp = $this->_pixie->table('urls')->where('url', $data['url'])->where('hash', $data['hash'])->select('id')->first();
			if (!$urlTemp || !isset($urlTemp->id)) {
				throw new \UnexpectedValueException('database error: unable to insert data', 500);
			}
			$urlID = $urlTemp->id;
		}

		if ($slug === null) {

			$hashidEngine = new Hashids($this->_config->urls['salt'], 3, $this->_config->urls['alphabet']);
			$slug         = $hashidEngine->encode($urlID);

		}

		$data = [
			'url_id'      => $urlID,
			'slug'        => trim($slug),
			'valid_to'    => ($expires !== null ? date($this->_config->timestampFormat['database'], strtotime($expires)) : null),
			'valid_from'  => ($starts !== null ? date($this->_config->timestampFormat['database'], strtotime($starts)) : null),
			'passthrough' => $passthrough,
			'enabled'     => 1,
		];

		$query = $this->_pixie->table('redirects');
		$query->onDuplicateKeyUpdate($data);

		// workaround for pixie not returning LAST_INSERT_ID(), if onDuplicate matches
		if (0 >= $redirectID = $query->insert($data)) {
			$redirectTemp = $this->_pixie->table('redirects')->where('url_id', $data['url_id'])->where('slug', $data['slug'])->select('id')->first();
			if (!$redirectTemp || !isset($redirectTemp->id)) {
				throw new \UnexpectedValueException('database error: unable to insert data', 500);
			}
			$redirectID = $redirectTemp->id;
		}

		return new URL($redirectID, $data['slug'], $url, $this->_config);
	}

	/**
	 * fetch url and handle visitor-count
	 * @param string $slug
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function getUrl(string $slug): URL {

		// skipt cache, and fetch directly from database
		if ($this->_cache === null) {
			$url = $this->_fetchURL($slug);
			$this->_trackVisit($url);
			return $url;
		}

		// try a cache lookup first
		$urlCache = $this->_cache->getItem('slug_' . str_replace(
			['{', '}', '(', ')', '/', '\\', '@', ':'],
			['|', '|', '|', '|', '.', '.', '-', '_'],
			$slug
		));

		if (null === $url = $urlCache->get()) {
			// fetch entry from db, and safe in cache
			$url = $this->_fetchURL($slug);

			$urlCache->set($url);
			$urlCache->expiresAfter($this->_config->cache['duration']);
			$this->_cache->save($urlCache);
		}

		$this->_trackVisit($url);
		return $url;
	}

	/**
	 * fetch url details from database
	 * @param string $slug
	 * @return URL
	 * @throws NotFound
	 */
	protected function _fetchURL(string $slug): URL{
		$query = $this->_pixie->table('redirects');

		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');

		$query->where('redirects.slug', trim($slug));
		$query->where('redirects.enabled', true);
		$query->where(function ($db) {
			$db->where($db->raw($this->_config->database['prefix'] . 'redirects.valid_to > NOW()'));
			$db->orWhereNull('redirects.valid_to');
		});
		$query->where(function ($db) {
			$db->where($db->raw($this->_config->database['prefix'] . 'redirects.valid_from < NOW()'));
			$db->orWhereNull('redirects.valid_from');
		});

		$query->select(['redirects.id', 'redirects.slug', 'redirects.passthrough', 'redirects.dereferrer', 'urls.url']);
		$url = $query->first();

		// slug not found
		if (!$url) {
			throw new NotFound('Unknown Slug, URL not found', 404);
		}

		return new URL($url->id, $url->slug, $url->url, $this->_config, [
			'passthrough' => (bool) $url->passthrough,
			'dereferrer'  => (bool) $url->dereferrer,
		]);
	}

	/**
	 * handle url tracking
	 * @param URL $url
	 * @return self
	 */
	protected function _trackVisit(URL $url): self {

		if (!$this->_config->tracking['enabled']) {
			return $this;
		}

		$visit = [
			'redirect_id' => $url->getRedirectID(),
			'visited'     => date($this->_config->timestampFormat['database']),
			'origin'      => $this->_network->getBaseURL($this->_config->rootURL),
		];

		// track user-data, if dnt is not set
		if (!$this->_config->tracking['respectDNT'] || !$this->_network->hasDNTSet()) {

			if ($this->_config->tracking['store']['ip']) {
				// track IP, if either user doesn't send DNT, or we decided to ignore it
				$visit['ip'] = inet_pton($this->_network->getIPAddr());
			}

			if ($this->_config->tracking['store']['userAgent']) {
				// save userAgent, if enabled
				$visit['user_agent'] = $this->_network->getUserAgent();
			}

			if ($this->_config->tracking['store']['referrer']) {
				// save userAgent, if enabled
				$visit['referrer'] = $this->_network->getReferrer();
			}

		}

		// save visitor data
		$query = $this->_pixie->table('visits');
		$query->insert($visit);

		return $this;
	}

	/**
	 * get internal Pixie Database QueryBuilder Object
	 * @return QueryBuilderHandler
	 */
	public function getDB(): QueryBuilderHandler {
		return $this->_pixie;
	}

}
