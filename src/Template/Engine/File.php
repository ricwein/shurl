<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Engine;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Exception\NotFound;

/**
 * provide File interaction methods
 */
class File {

    /**
     * @var string
     */
    protected $_basepath;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @param string $basepath
     * @param Config $config
     */
    public function __construct(string $basepath, Config $config) {
        $this->_basepath = rtrim($basepath, '/') . '/';
        $this->_config   = $config;
    }

    /**
     * @param  string   $filePath
     * @param  bool     $searchPath
     * @throws NotFound
     * @return string
     */
    public function read(string $filePath, bool $searchPath = false): string {
        if ($searchPath) {
            $filePath = $this->path($filePath);
        }

        if (false !== $content = @file_get_contents($this->_basepath . $filePath)) {
            return $content;
        }

        throw new NotFound('unable to read template', 404);
    }

    /**
     * @param  string   $filename
     * @param  bool     $dirOnly
     * @throws NotFound
     * @return string
     */
    public function path(string $filename, bool $dirOnly = false): string {
        $extension = ltrim($this->_config->views['extension'], '.');
        $fileNames = [];

        // name defined in routes, look them up
        if (isset($this->_config->views['route'][$filename])) {
            $fileNames[] = $this->_config->views['route'][$filename];
            $fileNames[] = trim(dirname($this->_config->views['route'][$filename]) . '/' . pathinfo($this->_config->views['route'][$filename], PATHINFO_FILENAME), '/.') . '.' . $extension;
        }

        // default lookup for filenames, witah and without default extension
        $fileNames[] = $filename;
        $fileNames[] = trim($filename, '/.') . '.' . $extension;
        $fileNames[] = trim(dirname($filename) . '/' . pathinfo($filename, PATHINFO_FILENAME), '/.') . '.' . $extension;
        $fileNames[] = trim(pathinfo($filename, PATHINFO_FILENAME), '/.') . '.' . $extension;

        // try each possible filename/path to find valid file
        foreach ($fileNames as $file) {
            if (file_exists($this->_basepath . '/' . $file) && is_readable($this->_basepath . '/' . $file)) {
                return $dirOnly ? dirname($file) : $file;
            }
        }

        throw new NotFound(sprintf('no file found for \'%s\' in \'%s\'', $filename, $this->_basepath), 404);
    }

    /**
     * @param  string   $filename
     * @param  bool     $dirOnly
     * @throws NotFound
     * @return string
     */
    public function fullPath(string $filename, bool $dirOnly = false): string {
        $filepath = $this->path($filename, $dirOnly);
        return $this->_basepath . $filepath;
    }

    /**
     * @param  string                    $filename
     * @throws \UnexpectedValueException
     * @return string
     */
    public function cachePath(string $filename): string {
        $path = $this->path($filename);
        return str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':'],
            ['.', '.', '.', '.', '.', '.', '.', '.'],
            $path
        );
    }

    /**
     * @param  string $filename
     * @return string
     */
    public function hash(string $filename): string {
        if (!$this->_config->views['useFileHash']) {
            return '';
        }
        return hash_file($this->_config->views['useFileHash'], $this->_basepath . $this->path($filename));
    }

    /**
     * @return string
     */
    public function getBasepath(): string {
        return $this->_basepath;
    }
}
