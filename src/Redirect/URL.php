<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Redirect;

use ricwein\shurl\Config\Config;

/**
 * represents a shurl URL object
 */
class URL extends \ArrayObject {

    /**
     * redirect ID
     * @var int
     */
    protected $id;

    /**
     * original URL
     * @var string
     */
    protected $original;

    /**
     * @var string
     */
    protected $slug;

    /**
     * @var string
     */
    protected $shortened;

    /**
     * @var array
     */
    protected $additionals;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @param int    $id
     * @param string $slug
     * @param string $original
     * @param string $mode
     * @param Config $config
     * @param array  $additionals
     */
    public function __construct(int $id, string $slug, string $original, string $mode, Config $config, array $additionals = []) {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, Rewrite::MODES, true)) {
            throw new \UnexpectedValueException(sprintf('"%s" is not a valid redirect mode', $mode));
        }

        $this->id          = $id;
        $this->slug        = $slug;
        $this->original    = $original;
        $this->additionals = $additionals;
        $this->mode        = $mode;

        $this->shortened = rtrim($config->rootURL, '/') . '/' . $slug;
    }

    /**
     * @return string
     */
    public function mode(): string {
        return $this->mode;
    }

    /**
     * @param  string     $name
     * @return mixed|null
     */
    public function additional(string $name) {
        if (array_key_exists($name, $this->additionals)) {
            return $this->additionals[$name];
        }
        return null;
    }

    /**
     * @return string
     */
    public function hash(): string {
        return hash(Config::getInstance()->urls['hash'], $this->original, false);
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return $this->original;
    }

    /**
     * @param  string     $name
     * @return string|int
     */
    public function __get(string $name) {
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
}
