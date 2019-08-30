<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Redirect;

use Klein\Response;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Cache;

/**
 * provides HTTP Networking methods
 */
class Rewrite
{

    /**
     * @var string[]
     */
    const MODES = ['redirect', 'html', 'passthrough'];

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var URL
     */
    protected $url;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @param Config   $config
     * @param URL      $url
     * @param Response $response
     */
    public function __construct(Config $config, URL $url, Response $response)
    {
        $this->config   = $config;
        $this->url      = $url;
        $this->response = $response;
    }

    /**
     * send redirect header
     *
     * this ends the current code execution!
     * @param  bool $permanent
     * @return void
     */
    public function rewrite(bool $permanent = false)
    {
        if ($permanent) {
            $this->response->status()->setCode(301);
            $this->response->header('Cache-Control', 'max-age=' . $this->config->cache['duration']);
        } else {
            $this->response->status()->setCode(302);
            $this->response->header('Pragma', 'no-cache');
            $this->response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $this->response->header('Expires', '0');
        }

        $this->response->header('Location', $this->url->original);
        $this->response->send();

        exit(0);
    }

    /**
     * allows server-side originURL fetching
     * and direct rendering to client, without redirection
     * @param  Cache|null $cache
     * @return void
     */
    public function passthrough(Cache $cache = null)
    {

        // list of headers which should be keept while passthrough
        $passthroughHeaders = array_flip([
            'Content-Type', 'Content-Length', 'ETag', 'Last-Modified',
        ]);

        if ($cache === null) {

            // fetch original header, but only re-set selected
            $headers = array_intersect_key(static::getHeaders($this->url->original, 1), $passthroughHeaders);
            foreach ($headers as $key => $value) {
                $this->response->header($key, $value);
            }

            // set cache-control for permanent files
            if (!$this->config->redirect['permanent']) {
                $this->response->header('Pragma', 'no-cache');
                $this->response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                $this->response->header('Expires', '0');
            } else {
                $this->response->header('Cache-Control', 'max-age=' . $this->config->cache['duration']);
            }

            // since we don't want to cache here, we directly print read lines
            readfile($this->url->original);

            exit(0);
        }

        $contentCache = $cache->getItem('url_' . $this->url->hash());
        if (null === $ressource = $contentCache->get()) {

            // fetch orignal headers and content
            $ressource = [
                'headers' => array_intersect_key(static::getHeaders($this->url->original, 1), $passthroughHeaders),
                'content' => file_get_contents($this->url->original),
            ];

            $contentCache->set($ressource);
            $contentCache->expiresAfter($this->config->cache['duration']);
            $cache->save($contentCache);
        }

        // fetch original header, but only re-set selected
        $headers = array_intersect_key($ressource['headers'], $passthroughHeaders);
        foreach ($headers as $key => $value) {
            $this->response->header($key, $value);
        }

        if (!$this->config->redirect['permanent']) {
            $this->response->header('Pragma', 'no-cache');
            $this->response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $this->response->header('Expires', '0');
        } else {
            $this->response->header('Cache-Control', 'max-age=' . $this->config->cache['duration']);
        }

        $this->response->body($ressource['content']);
        $this->response->send();

        exit(0);
    }

    /**
     * @param  string $url
     * @return array
     */
    protected static function getHeaders(string $url): array
    {
        $headers = get_headers($url, 1);

        if (!$headers) {
            return [];
        }

        foreach ($headers as &$value) {
            if (is_array($value)) {
                $value = reset($value);
            }
        }
        return $headers;
    }
}
