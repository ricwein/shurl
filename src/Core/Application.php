<?php

namespace ricwein\shurl\Core;

use Klein\Klein;
use Klein\Request;
use Klein\Response;
use ricwein\shurl\Config\Config;

/**
 * shurl application,
 * using the shurl core to provide
 */
class Application {

	/**
	 * @var Core
	 */
	protected $core;

	/**
	 * init new shurl Core
	 * @param null|Config|Core $init
	 */
	public function __construct($init = null) {
		if ($init instanceof Config) {
			$this->core = new Core($init);
		} elseif ($init instanceof Core) {
			$this->core = $init;
		} else {
			$this->core = new Core();
		}
	}

	/**
	 * parse reqeusted shortened URL and redirect to original, if available
	 * @return void
	 * @throws \Throwable
	 */
	public function route() {

		$klein = new Klein();

		// match and redirect slugs
		$klein->respond('GET', '/[:slug](/)?', function (Request $request, Response $response) {
			$url = $this->core->getUrl($request->slug);

			$this->core->track($url, $request);
			$this->core->redirect($url, $response);
		});

		// match and preview slugs
		$klein->respond('GET', '/preview/[:slug](/)?', function (Request $request) {
			$url = $this->core->getUrl($request->slug);

			$this->core->track($url, $request);
			$this->core->viewTemplate('preview', [
				'url' => $url,
			]);
		});

		// match and preview slugs
		$klein->respond('GET', '/api/[:slug](/)?', function (Request $request, Response $response) {
			$url = $this->core->getUrl($request->slug);

			$this->core->track($url, $request);
			$response->json([
				'id'       => $url->id,
				'slug'     => $url->slug,
				'original' => $url->original,
			]);
			exit(0);
		});

		// match scss assets, and parse them
		$klein->respond('GET', '/assets/css/[:stylesheet].css', function (Request $request, Response $response) {
			$this->core->viewAsset($request->stylesheet, $response);
		});

		// show welcome page as default
		$klein->respond('/', function (Request $request) {
			$this->core->viewWelcome();
		});

		// run dispatcher and handle thrown exceptions
		try {
			$klein->dispatch();
		} catch (\Throwable $throwable) {
			$this->core->handleException($throwable);
		}

	}

}
