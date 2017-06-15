<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ricwein\shurl\Core\Router;

$shurl = new Router();
$shurl->dispatch();
