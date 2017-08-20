<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Processor;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Template\Engine\Worker;

/**
 * trims html output
 */
class Minify extends Worker {

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * @var string[]
     */
    const REGEX = [
        '/\>[^\S ]+/s'      => '>', // strip whitespaces after tags, except space
        '/[^\S ]+\</s'      => '<', // strip whitespaces before tags, except space
        '/(\s)+/s'          => '\\1', // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/' => '', // Remove HTML comments
    ];

    /**
     * @param  string $content
     * @return string
     */
    protected function replace(string $content): string {
        if ($this->config->development) {
            return $content;
        }

        return trim(preg_replace(array_keys(static::REGEX), array_values(static::REGEX), $content));
    }
}
