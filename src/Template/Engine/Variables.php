<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Template\Engine;

/**
 * extend base worker for Variables
 */
abstract class Variables extends Worker {

    /**
     * @var string[]
     */
    const REGEX = ['/\{\{\s*', '\s*\}\}/'];
}
