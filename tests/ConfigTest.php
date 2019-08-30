<?php declare(strict_types = 1);

namespace tests;

use PHPUnit\Framework\TestCase;
use ricwein\shurl\Config\Config;

/**
 * test shurl Config class
 *
 * @covers Config
 */
class ConfigTest extends TestCase
{
    /**
     * test config getters and setters
     */
    public function testSetter()
    {
        $config = Config::getInstance();

        $this->assertInternalType('array', $config->get());

        $config->name = 'shurl';
        $this->assertSame('shurl', $config->name);

        $config->name = 'test';
        $this->assertSame('test', $config->name);
    }

    /**
     * testing config singleton usage
     */
    public function testSingleton()
    {
        $config = Config::getInstance(['unittest' => true]);
        $this->assertSame(true, $config->unittest);

        $config = Config::getInstance();
        $this->assertSame(true, $config->unittest);
        $config->unittest = ['yay'];

        $config = Config::getInstance();
        $this->assertSame(['yay'], $config->unittest);
    }
}
