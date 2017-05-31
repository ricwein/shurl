<?php

namespace ricwein\shurl\Template\Filter;

use ricwein\shurl\Template\Engine\Variables;

/**
 * simple Template parser with Twig-like syntax
 */
class Bindings extends Variables {

	/**
	 * @param string $content
	 * @param array|object|null $bindings varaibles to be replaced
	 * @param int $currentDepth
	 * @return string
	 */
	protected function replace(string $content, $bindings = null, int $currentDepth = 0): string {

		if ($bindings === null) {
			return $content;
		}

		// iterate through all values
		foreach ($bindings as $key => $value) {
			if ($value === null || is_scalar($value)) {

				// replace values if matching with the following excaped keys
				$content = $this->_strReplace($key, $value, $content);

			} elseif (is_object($value) && method_exists($value, $key)) {

				// replace variable with result of methods
				$content = $this->_strReplace($key, call_user_func_array([$value, $key], []), $content);

			} elseif ((is_array($value) || is_object($value)) && $currentDepth <= self::MAX_DEPTH) {

				// recursive call to apply() if value is iteraterable
				$content = $this->replace($content, $value, false, $currentDepth + 1);

			} else {

				// catch all other data-types
				$content = $this->_strReplace($key, '\'' . gettype($value) . '\'', $content);

			}
		}

		return $content;
	}

}
