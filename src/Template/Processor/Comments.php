<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Processor;

use ricwein\shurl\Template\Engine\Variables;

/**
 * simple Template parser with Twig-like syntax
 */
class Comments extends Variables
{

    /**
     * @var string[]
     */
    const REGEX = ['/\{#\s*', '\s*#\}/'];

    /**
     * @param  string $content
     * @param  bool   $inline  convert comments to html inline comments, instead of removing them
     * @return string
     */
    protected function replace(string $content, bool $inline = false): string
    {
        $regex = $this->getRegex('(.*)');
        return preg_replace_callback($regex, function (array $match) use ($inline): string {
            return $inline && isset($match[1]) ? '<!-- ' . trim($match[1]) . ' -->' : '';
        }, $content);
    }
}
