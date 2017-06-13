<?php

namespace ricwein\shurl\Template\Processor;

use ricwein\shurl\Template\Engine\Functions;

/**
 * simple Template parser with Twig-like syntax
 */
class Includes extends Functions {

	/**
	 * @param string $content
	 * @param int $currentDepth
	 * @return string
	 */
	protected function replace(string $content, int $currentDepth = 0): string{

		// include other template files
		$content = preg_replace_callback($this->getRegex('include(.*)'), function ($match) use ($currentDepth) {
			$filecontent = $this->_file->read(trim($match[1], '\'" '), true);
			if ($currentDepth <= self::MAX_DEPTH) {
				return $this->replace($filecontent, $currentDepth + 1);
			}

			return $filecontent;
		}, $content);

		return $content;
	}

}
