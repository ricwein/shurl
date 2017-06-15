<?php

namespace ricwein\shurl\Core;

use Hashids\Hashids;
use Klein\Request;
use Klein\Response;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;
use ricwein\shurl\Config\Config;
use ricwein\shurl\Exception\DatabaseUnreachable;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Redirect\Rewrite;
use ricwein\shurl\Redirect\URL;

/**
 * shurl core class
 */
class Core {

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var QueryBuilderHandler
	 */
	protected $pixie = null;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * @var Cache|null
	 */
	protected $cache = null;

	/**
	 * init new shurl Core
	 * @param Config|null $config
	 */
	public function __construct(Config $config = null) {

		// allocate config instance
		if ($config === null) {
			$this->config = Config::getInstance();
		} else {
			$this->config = $config;
		}

		if ($this->config->cache['enabled']) {
			$this->cache = new Cache($this->config->cache['engine'], $this->config->cache['config']);
			$this->cache->setPrefix($this->config->cache['prefix']);
		}

		// init new monolog logger
		$this->logger = new Logger($this->config->name);
		$this->logger->pushHandler(new StreamHandler(
			__DIR__ . '/../../' . ltrim($this->config->log['path'], '/'),
			$this->config->log['severity']
		));

		// register as global fallback handler
		if (!$this->config->development) {
			ErrorHandler::register($this->logger);
		}
	}

	/**
	 * load and redirect to given url
	 * @param URL $url
	 * @param Response $response
	 * @return void
	 * @throws \Throwable
	 */
	public function redirect(URL $url, Response $response) {

		$rewrite = new Rewrite($this->config, $url, $response);
		switch ($url->mode()) {
			case 'passthrough':$rewrite->passthrough($this->config->cache['passthrough'] ? $this->cache : null);
			case 'redirect':$rewrite->rewrite($this->config->redirect['permanent'] && !$this->config->development);
			default:throw new \UnexpectedValueException(sprintf('invalid redirect mode \'%s\'', $url->mode()), 500);
		}
	}

	/**
	 * @param \Throwable $throwable
	 * @return void
	 */
	public function logException(\Throwable $throwable) {

		if ($this->config->development) {
			throw $throwable;
		}

		$this->logger->addRecord(
			($throwable->getCode() > 0 && $throwable->getCode() < 500 ? Logger::NOTICE : Logger::ERROR),
			$throwable->getMessage(),
			['exception' => $throwable]
		);
	}

	/**
	 * @return int
	 */
	public function getURLCount(): int {

		if ($this->cache === null) {
			return $this->countURLs();
		}

		$countCache = $this->cache->getItem('count');

		if (null === $count = $countCache->get()) {
			$count = $this->countURLs();
			$countCache->set($count);
			$countCache->expiresAfter(60);
			$this->cache->save($countCache);
		}

		return $count;
	}

	/**
	 * @return int
	 */
	protected function countURLs(): int{
		$query = $this->db->table('redirects');
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
	 * @param  string $redirectMode Rewrite::MODES
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function addUrl(string $url, string $slug = null, string $starts = null, string $expires = null, string $redirectMode): URL{

		$url          = trim($url);
		$redirectMode = strtolower(trim($redirectMode));

		if (!in_array($redirectMode, Rewrite::MODES)) {
			throw new \UnexpectedValueException(sprintf('"%s" is not a valid redirect mode', $redirectMode));
		}

		$data = [
			'url'  => $url,
			'hash' => hash($this->config->urls['hash'], $url, false),
		];

		$query = $this->db->table('urls');
		$query->onDuplicateKeyUpdate($data);

		// workaround for db not returning LAST_INSERT_ID(), if onDuplicate matches
		if (0 >= $urlID = $query->insert($data)) {
			$urlTemp = $this->db->table('urls')->where('url', $data['url'])->where('hash', $data['hash'])->select('id')->first();
			if (!$urlTemp || !isset($urlTemp->id)) {
				throw new \UnexpectedValueException('database error: unable to insert data', 500);
			}
			$urlID = $urlTemp->id;
		}

		if ($slug === null) {

			$hashidEngine = new Hashids($this->config->urls['salt'], 3, $this->config->urls['alphabet']);
			$slug         = $hashidEngine->encode($urlID);

		} elseif (in_array($slug, $this->config->urls['reserved'])) {
			throw new \UnexpectedValueException('the given slug is not allowed', 409);
		}

		$data = [
			'url_id'     => $urlID,
			'slug'       => trim($slug),
			'valid_to'   => ($expires !== null ? date($this->config->timestampFormat['database'], strtotime($expires)) : null),
			'valid_from' => ($starts !== null ? date($this->config->timestampFormat['database'], strtotime($starts)) : null),
			'mode'       => $redirectMode,
			'enabled'    => 1,
		];

		$query = $this->db->table('redirects');
		$query->onDuplicateKeyUpdate($data);

		// workaround for db not returning LAST_INSERT_ID(), if onDuplicate matches
		if (0 >= $redirectID = $query->insert($data)) {
			$redirectTemp = $this->db->table('redirects')->where('url_id', $data['url_id'])->where('slug', $data['slug'])->select('id')->first();
			if (!$redirectTemp || !isset($redirectTemp->id)) {
				throw new \UnexpectedValueException('database error: unable to insert data', 500);
			}
			$redirectID = $redirectTemp->id;
		}

		return new URL($redirectID, $data['slug'], $url, $redirectMode, $this->config);
	}

	/**
	 * fetch url and handle visitor-count
	 * @param string $slug
	 * @return URL
	 * @throws \UnexpectedValueException
	 */
	public function getUrl(string $slug): URL {

		// skipt cache, and fetch directly from database
		if ($this->cache === null) {
			$url = $this->fetchURL($slug);
			return $url;
		}

		// try a cache lookup first
		$urlCache = $this->cache->getItem('slug_' . str_replace(
			['{', '}', '(', ')', '/', '\\', '@', ':'],
			['|', '|', '|', '|', '.', '.', '-', '_'],
			$slug
		));

		if (null === $url = $urlCache->get()) {
			// fetch entry from db, and safe in cache
			$url = $this->fetchURL($slug);

			$urlCache->set($url);
			$urlCache->expiresAfter($this->config->cache['duration']);
			$this->cache->save($urlCache);
		}

		return $url;
	}

	/**
	 * fetch url details from database
	 * @param string $slug
	 * @return URL
	 * @throws NotFound
	 */
	protected function fetchURL(string $slug): URL{
		$query = $this->db->table('redirects');

		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');

		$query->where('redirects.slug', trim($slug));
		$query->where('redirects.enabled', true);
		$query->where(function ($db) {
			$db->where($db->raw($this->config->database['prefix'] . 'redirects.valid_to > NOW()'));
			$db->orWhereNull('redirects.valid_to');
		});
		$query->where(function ($db) {
			$db->where($db->raw($this->config->database['prefix'] . 'redirects.valid_from < NOW()'));
			$db->orWhereNull('redirects.valid_from');
		});

		$query->select(['redirects.id', 'redirects.slug', 'redirects.mode', 'urls.url']);
		$url = $query->first();

		// slug not found
		if (!$url) {
			throw new NotFound(sprintf('Unknown Slug \'%s\', URL not found', $slug), 404);
		}

		return new URL($url->id, $url->slug, $url->url, $url->mode, $this->config);
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	public function getBaseURL(Request $request): string{
		$schema = ($request->isSecure() ? 'https' : 'http');

		if (false === $host = $request->server()->get('HTTP_HOST', false)) {
			return $this->config->rootURL;
		}

		if (false === $path = $request->server()->get('REQUEST_URI', false)) {
			return $this->config->rootURL;
		}

		$host = rtrim($host, '/');
		$path = trim(str_replace($request->uri(), '', $path), '/');

		return $schema . '://' . rtrim($host . '/' . $path, '/');

	}

	/**
	 * handle url tracking
	 * @param URL $url
	 * @param Request $request
	 * @return self
	 */
	public function track(URL $url, Request $request): self {

		if (!$this->config->tracking['enabled']) {
			return $this;
		}

		$visit = [
			'redirect_id' => $url->id,
			'visited'     => date($this->config->timestampFormat['database']),
			'origin'      => $this->getBaseURL($request),
		];

		// track user-data, if dnt is not set
		if (!$this->config->tracking['respectDNT'] || ((int) $request->headers()->get('HTTP_DNT') === 1)) {

			if ($this->config->tracking['store']['ip']) {
				// track IP, if either user doesn't send DNT, or we decided to ignore it
				$visit['ip'] = inet_pton($request->ip());
			}

			if ($this->config->tracking['store']['userAgent']) {
				// save userAgent, if enabled
				$visit['user_agent'] = $request->userAgent();
			}

			if ($this->config->tracking['store']['referrer']) {
				// save userAgent, if enabled
				$visit['referrer'] = $request->headers()->get('HTTP_REFERER');
			}

		}

		// save visitor data
		try {
			$query = $this->db->table('visits');
			$query->insert($visit);
		} catch (\Throwable $exception) {
			if ($this->config->tracking['skipOnError'] && $exception instanceof DatabaseUnreachable) {
				return $this;
			}
			throw $exception;
		}

		return $this;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name) {

		// exceptional handling for lazy db loading
		if ($name === 'db') {
			return $this->getDB();
		}

		if (property_exists($this, $name)) {
			return $this->$name;
		}
		return null;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset(string $name): bool {
		return property_exists($this, $name);
	}

	/**
	 * lazy-loads database connection
	 * @throws \Exception
	 * @return QueryBuilderHandler
	 */
	protected function getDB(): QueryBuilderHandler {
		try {

			if ($this->pixie !== null) {
				return $this->pixie;
			}

			$this->pixie = new QueryBuilderHandler(new Connection(
				$this->config->database['driver'],
				$this->config->database
			));
			return $this->pixie;

		} catch (\Throwable $exception) {

			throw new DatabaseUnreachable('The Database Server is currently not reachable, please try again later', 503, $exception);

		}
	}

}
