<?php

namespace ricwein\shurl\Core;

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
		$this->core->route();
	}

}
