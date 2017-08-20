<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Engine;

/**
 * provide base worker
 */
abstract class Worker {

    /**
     * @var string[]
     */
    const REGEX = [];

    /**
     * @var int
     */
    const MAX_DEPTH = 64;

    /**
     * @param  string $name
     * @return string
     */
    protected function getRegex(string $name): string {
        return implode($name, static::REGEX);
    }

    /**
     * replace inline variables in string through values
     * supports twig, shell and swift string-variables
     * @param  int|float|string $key
     * @param  int|float|string $value
     * @param  string           $string
     * @return string
     */
    protected function _strReplace($key, $value, string $string): string {

        // mitigate null-byte attacks
        $key = str_replace(chr(0), '', $key);

        return (string) trim(preg_replace($this->getRegex($key), (string) $value, $string));
    }

    /**
     * provides public access to private and protected methods as non-static calls
     * @param  string $method
     * @param  array  $args
     * @return mixed
     */
    public function __call(string $method, array $args) {
        return call_user_func_array([$this, $method], $args);
    }

    /**
     * provides public static access to private and protected methods implicitly calling self::instance()
     * @param  string $method
     * @param  array  $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args) {
        return call_user_func_array([(new static()), $method], $args);
    }
}
