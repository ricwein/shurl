<?php declare (strict_types = 1);

namespace tests;

use PHPUnit\Framework\TestCase;
use ricwein\shurl\Template\Processor\Bindings;
use ricwein\shurl\Template\Processor\Comments;
use ricwein\shurl\Template\Processor\Implode;

/**
 * test shurl Config class
 *
 * @author Richard Weinhold
 * @covers Template
 */
class TemplateTest extends TestCase {

	/**
	 * test to comment template-processor
	 */
	public function testCommentProcessor() {
		$processor = new Comments();
		$input     = '{# test #} works';

		$output = $processor->replace($input, false);
		$this->assertSame(' works', $output);

		$output = $processor->replace($input, true);
		$this->assertSame('<!-- test --> works', $output);
	}

	/**
	 * test to bindings template-processor
	 */
	public function testBindingProcessor() {
		$processor = new Bindings();
		$input     = '{{ test }} works';

		$output = $processor->replace($input, []);
		$this->assertSame(' works', $output);

		$output = $processor->replace($input, ['test' => 'yay']);
		$this->assertSame('yay works', $output);

		$input  = '{{ test.nested }} works';
		$output = $processor->replace($input, ['test' => ['nested' => 'also']]);
		$this->assertSame('also works', $output);

		$bindingObj         = new \stdClass();
		$bindingObj->nested = 'obj';
		$output             = $processor->replace($input, ['test' => $bindingObj]);
		$this->assertSame('obj works', $output);

		$bindingObj->nested = ['sub' => 'yyyaaayyy'];
		$input              = '{{ test.nested.sub }} works';
		$output             = $processor->replace($input, ['test' => $bindingObj]);
		$this->assertSame('yyyaaayyy works', $output);
	}

	/**
	 * test to implode template-processor
	 */
	public function testImplodeProcessor() {
		$processor = new Implode();

		$input  = '{{ array | implode(" ") }} yay';
		$output = $processor->replace($input, ['array' => ['test', 'succeeded']]);
		$this->assertSame('test succeeded yay', $output);

		$input          = '{{ obj | implode(" ") }} yay';
		$object         = new \stdClass();
		$object->first  = 'test';
		$object->second = 'succeeded';
		$output         = $processor->replace($input, ['obj' => $object]);
		$this->assertSame('test succeeded yay', $output);

		$input  = '{{ array.nested | implode(", ") }} yay';
		$output = $processor->replace($input, ['array' => ['nested' => ['test', 'succeeded']]]);
		$this->assertSame('test, succeeded yay', $output);
	}

}
