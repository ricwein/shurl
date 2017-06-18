<?php declare (strict_types = 1);

namespace tests\Template;

use PHPUnit\Framework\TestCase;
use ricwein\shurl\Config\Config;
use ricwein\shurl\Template\Processor\Includes;
use ricwein\shurl\Template\Template;

/**
 * test shurl Template Engine
 *
 * @covers Template
 */
class TemplateTest extends TestCase {

	/**
	 * test loading of template files
	 */
	public function testTemplateLoading() {

		$config = Config::getInstance(['views' => [
			'path'  => __DIR__ . '/dummytemplates',
			'route' => ['routeTest' => 'test'],
		]]);

		$engine = new Template($config, null);

		// simple variable parsing
		$output = $engine->make('test.html.twig', ['test' => ['nested' => 'succeeded']]);
		$this->assertSame('test succeeded', $output);

		// template extensions
		$output = $engine->make('test', ['test' => ['nested' => 'succeeded']]);
		$this->assertSame('test succeeded', $output);

		// routing
		$output = $engine->make('routeTest', ['test' => ['nested' => 'succeeded']]);
		$this->assertSame('test succeeded', $output);
	}

	/**
	 * test recursive loading of template files
	 */
	public function testTemplateRecursiveLoading() {

		$config = Config::getInstance(['views' => [
			'path' => __DIR__ . '/dummytemplates',
		]]);

		$engine = new Template($config, null);

		// simple include
		$output = $engine->make('include', ['test' => ['nested' => 'succeeded']]);
		$this->assertSame('include test succeeded', $output);

		// recursive include with depth canceling
		$output = $engine->make('recursive');
		$this->assertTrue(false !== $pos = strpos($output, '{% include \'recursive\' %}'));
		$output = trim(substr($output, 0, $pos));
		$this->assertSame(Includes::MAX_DEPTH, substr_count($output, 'recursive') - 1);
	}

}
