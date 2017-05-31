<?php

namespace ricwein\shurl\Template\Engine;

/**
 * extend base worker for Functions
 */
abstract class Functions extends Worker {

	/**
	 * @var File
	 */
	protected $_file;

	/**
	 * @param File $file
	 */
	public function __construct(File $file) {
		$this->_file = $file;
	}

	/**
	 * @var string[]
	 */
	const REGEX = ['/\{%\s*', '\s*%\}/'];

}
