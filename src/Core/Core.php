<?php
/**
 * @author Richard Weinhold
 */
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
     * @param  URL        $url
     * @param  Response   $response
     * @throws \Throwable
     * @return void
     */
    public function redirect(URL $url, Response $response) {
        $rewrite = new Rewrite($this->config, $url, $response);
        switch ($url->mode()) {
            case 'passthrough':$rewrite->passthrough($this->config->redirect['cachePassthrough'] ? $this->cache : null);
            // no break
            case 'redirect':$rewrite->rewrite($this->config->redirect['permanent'] && !$this->config->development);
            // no break
            default:throw new \UnexpectedValueException(sprintf('invalid redirect mode \'%s\'', $url->mode()), 500);
        }
    }

    /**
     * @param  \Throwable $throwable
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
    public function getUrlCount(): int {
        if ($this->cache === null) {
            return $this->countUrls();
        }

        $countCache = $this->cache->getItem('count');

        if (null === $count = $countCache->get()) {
            $count = $this->countUrls();
            $countCache->set($count);
            $countCache->expiresAfter(60);
            $this->cache->save($countCache);
        }

        return $count;
    }

    /**
     * @return int
     */
    protected function countUrls(): int {
        $query = $this->db->table('redirects');
        $query->select([$query->raw('COUNT(*) as count')]);
        $now = new \DateTime();

        // only select currently enabled entries
        $query->where('enabled', '=', true);
        $query->where(function ($db) use ($now) {
            $db->where('valid_to', '>', $now->format($this->config->timestampFormat['database']));
            $db->orWhereNull('valid_to');
        });
        $query->where(function ($db) use ($now) {
            $db->where('valid_from', '<', $now->format($this->config->timestampFormat['database']));
            $db->orWhereNull('valid_from');
        });

        return $query->first()->count;
    }

    /**
     * add new URL to index
     * @param  string                    $url
     * @param  string|null               $slug
     * @param  string|null               $starts
     * @param  string|null               $expires
     * @param  string                    $redirectMode Rewrite::MODES
     * @throws \UnexpectedValueException
     * @return URL
     */
    public function addUrl(string $url, string $slug = null, string $starts = null, string $expires = null, string $redirectMode): URL {
        $url          = trim($url);
        $now          = new \DateTime();
        $redirectMode = strtolower(trim($redirectMode));

        if (!in_array($redirectMode, Rewrite::MODES, true)) {
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
        } elseif (in_array($slug, $this->config->urls['reserved'], true)) {
            throw new \UnexpectedValueException('the given slug is not allowed', 409);
        }

        $data = [
            'url_id'     => $urlID,
            'slug'       => trim($slug),
            'created'    => $now->format($this->config->timestampFormat['database']),
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
     * @param  string                    $slug
     * @throws \UnexpectedValueException
     * @return URL
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
     * @param  string   $slug
     * @throws NotFound
     * @return URL
     */
    protected function fetchURL(string $slug): URL {
        $query = $this->db->table('redirects');
        $now   = new \DateTime();

        $query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');

        $query->where('redirects.slug', trim($slug));
        $query->where('redirects.enabled', true);
        $query->where(function ($db) use ($now) {
            $db->where('redirects.valid_to', '>', $now->format($this->config->timestampFormat['database']));
            $db->orWhereNull('redirects.valid_to');
        });
        $query->where(function ($db) use ($now) {
            $db->where('redirects.valid_from', '<', $now->format($this->config->timestampFormat['database']));
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
     * @param  Request $request
     * @return string
     */
    public function getBaseURL(Request $request): string {
        $schema = ($request->isSecure() ? 'https' : 'http');

        if (false === $host = $request->server()->get('HTTP_HOST', false)) {
            return rtrim($this->config->rootURL, '/');
        }

        if (false === $path = $request->server()->get('REQUEST_URI', false)) {
            return rtrim($this->config->rootURL, '/');
        }

        $host = rtrim($host, '/');
        $path = trim(str_replace($request->uri(), '', $path), '/');

        return $schema . '://' . rtrim($host . '/' . $path, '/');
    }

    /**
     * handle url tracking
     * @param  URL     $url
     * @param  Request $request
     * @return self
     */
    public function track(URL $url, Request $request): self {
        if (!$this->config->tracking['enabled']) {
            return $this;
        }

        $now = new \DateTime();

        $visit = [
            'redirect_id' => $url->id,
            'visited'     => $now->format($this->config->timestampFormat['database']),
            'origin'      => $this->getBaseURL($request),
        ];

        // track user-data, if dnt is not set
        if (!$this->config->tracking['respectDNT'] || ((int) $request->headers()->get('HTTP_DNT') === 1)) {

            // track IP, if either user doesn't send DNT, or we decided to ignore it
            if ($this->config->tracking['store']['ip']) {
                $visit['ip'] = inet_pton($request->ip());
            }

            // save userAgent, if enabled
            if ($this->config->tracking['store']['userAgent']) {
                $visit['user_agent'] = $request->userAgent();
            }

            // save userAgent, if enabled
            if ($this->config->tracking['store']['referrer']) {
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
     * @param  string $name
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
     * @param  string $name
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
        if ($this->pixie !== null) {
            return $this->pixie;
        }

        $dbConfig = $this->config->database;

        if (stripos($dbConfig['driver'], 'sqlite') !== false) {
            $dbConfig['database'] = __DIR__ . '/../../resources/database/' . $dbConfig['database'] . '.sqlite';
        }

        $this->pixie = new QueryBuilderHandler(new Connection(
            $dbConfig['driver'],
            $dbConfig
        ));

        return $this->pixie;
    }
}
