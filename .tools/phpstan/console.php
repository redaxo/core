<?php

use Redaxo\Core\Console\Application;
use Redaxo\Core\Console\CommandLoader;

$project = require dirname(__DIR__) . '/bootstrap.php';

$application = new Application($project);
$application->setCommandLoader(new CommandLoader());

return $application;
