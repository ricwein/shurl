<?php declare(strict_types = 1);

namespace tests\Template;

use PHPUnit\Framework\TestCase;
use ricwein\shurl\Config\Config;
use ricwein\shurl\Template\Processor;

/**
 * test shurl Template Engine processors
 *
 * @covers Processor
 */
class ProcessorTest extends TestCase
{
    /**
     * test to comment template-processor
     */
    public function testCommentProcessor()
    {
        $processor = new Processor\Comments();
        $input     = '{# test #} works';

        $output = $processor->replace($input, false);
        $this->assertSame(' works', $output);

        $output = $processor->replace($input, true);
        $this->assertSame('<!-- test --> works', $output);
    }

    /**
     * test to bindings template-processor
     */
    public function testBindingProcessor()
    {
        $processor = new Processor\Bindings();
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
    public function testImplodeProcessor()
    {
        $processor = new Processor\Implode();

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

    /**
     * test to minify html template-processor
     */
    public function testMinifyProcessor()
    {
        $processor = new Processor\Minify(Config::getInstance());

        $input = implode(PHP_EOL, [
            ' test ', // leading and trailing whitespaces
            '   done   ', // multiple whitespaces
        ]);
        $output = $processor->replace($input);
        $this->assertSame('test done', $output);

        $input = implode(PHP_EOL, [
            'test    ',
            '<a href="#">',
            '<b>html</b>',
            '</a>',
            '<img src="#"    />',
        ]);
        $output = $processor->replace($input);
        $this->assertSame('test <a href="#"><b>html</b></a><img src="#" />', $output);
    }
}
