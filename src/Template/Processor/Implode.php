<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Processor;

use ricwein\shurl\Template\Engine\Variables;

/**
 * implode method, allowing array joining with given glue
 */
class Implode extends Variables
{
    /**
     * @param  string            $content
     * @param  array|object|null $bindings varaibles to be replaced
     * @return string
     */
    protected function replace(string $content, $bindings = null): string
    {
        if ($bindings === null) {
            return $content;
        }

        // replace all variables
        $content = preg_replace_callback($this->getRegex('([^}]+)\|\s*implode\([\"|\']([^}]+)[\"|\']\)'), function ($match) use ($bindings): string {
            $variable = explode('.', trim($match[1]));
            $glue     = stripslashes($match[2]);

            // traverse template variable
            $current = $bindings;
            foreach ($variable as $value) {

                // match against current bindings tree
                if (is_array($current) && array_key_exists($value, $current)) {
                    $current = $current[$value];
                } elseif (is_object($current) && (property_exists($current, $value) || isset($current->$value))) {
                    $current = $current->$value;
                } elseif (is_object($current) && method_exists($current, $value)) {
                    $current = $current->$value();
                } else {
                    break; // no more entries found
                }
            }

            // check for return type
            if ($current === $bindings) {
                return '';
            } elseif (is_array($current) || is_object($current)) {
                return implode($glue, (array) $current);
            }
            return '';
        }, $content);

        return $content;
    }
}
