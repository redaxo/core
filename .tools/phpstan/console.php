<?php

use Redaxo\Core\Console\Application;
use Redaxo\Core\Console\CommandLoader;

if (!defined('REX_MIN_PHP_VERSION')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

$application = new Application();
$application->setCommandLoader(new CommandLoader());

return $application;
