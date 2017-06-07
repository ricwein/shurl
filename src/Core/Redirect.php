<?php

namespace ricwein\shurl\Core;
use Klein\Response;
use ricwein\shurl\Config\Config;

/**
 * provides HTTP Networking methods
 */
class Redirect {

	/**
	 * send redirect header
	 *
	 * this ends the current code execution!
	 * @param Config $config
	 * @param URL $url
	 * @param Response $response
	 * @param bool $permanent
	 * @return void
	 */
	public function rewrite(Config $config, URL $url, Response $response, bool $permanent = false) {

		if ($permanent) {

			http_response_code(301);
			$response->header('Cache-Control', 'max-age=' . $config->cache['duration']);

		} else {

			http_response_code(302);
			$response->header('Pragma', 'no-cache');
			$response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
			$response->header('Expires', '0');
		}

		$response->header('Location', $url->getOriginal());
		$response->send();
	}

	/**
	 * allows server-side originURL fetching
	 * and direct rendering to client, without redirection
	 * @param Config $config
	 * @param URL $url
	 * @param Response $response
	 * @param Cache|null $cache
	 * @return void
	 */
	public function passthrough(Config $config, URL $url, Response $response, Cache $cache = null) {

		// list of headers which should be keept while passthrough
		$passthroughHeaders = array_flip([
			'Content-Type', 'Content-Length', 'ETag', 'Last-Modified',
		]);

		if ($cache === null) {

			// fetch original header, but only re-set selected
			$headers = array_intersect_key($this->getHeaders($url->getOriginal(), 1), $passthroughHeaders);
			foreach ($headers as $key => $value) {
				$response->header($key, $value);
			}

			// set cache-control for permanent files
			if (!$config->redirect['permanent']) {
				$response->header('Pragma', 'no-cache');
				$response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
				$response->header('Expires', '0');
			} else {
				$response->header('Cache-Control', 'max-age=' . $config->cache['duration']);
			}

			// since we don't want to cache here, we directly print read lines
			readfile($url->getOriginal());

			exit(0);
		}

		$contentCache = $cache->getItem('url_' . $url->getHash());
		if (null === $ressource = $contentCache->get()) {

			// fetch orignal headers and content
			$ressource = [
				'headers' => array_intersect_key($this->getHeaders($url->getOriginal(), 1), $passthroughHeaders),
				'content' => file_get_contents($url->getOriginal()),
			];

			$contentCache->set($ressource);
			$contentCache->expiresAfter($config->cache['duration']);
			$cache->save($contentCache);
		}

		// fetch original header, but only re-set selected
		$headers = array_intersect_key($ressource['headers'], $passthroughHeaders);
		foreach ($headers as $key => $value) {
			$response->header($key, $value);
		}

		if (!$config->redirect['permanent']) {
			$response->header('Pragma', 'no-cache');
			$response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
			$response->header('Expires', '0');
		} else {
			$response->header('Cache-Control', 'max-age=' . $config->cache['duration']);
		}

		$response->body($ressource['content']);
		$response->send();
	}

	/**
	 * @param string $url
	 * @return array
	 */
	protected function getHeaders(string $url): array{
		$headers = get_headers($url, 1);
		foreach ($headers as &$value) {
			if (is_array($value)) {
				$value = reset($value);
			}
		}
		return $headers;
	}
}
