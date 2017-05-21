<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ricwein\shurl\config\Config;
use ricwein\shurl\core\Application;

$app = new Application(Config::getInstance());
$app->route();
