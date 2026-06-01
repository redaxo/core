<?php

use Project\Project;
use Redaxo\Core\Environment;
use Redaxo\Core\ErrorHandler;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$project = new Project(Environment::Console);

$project->bootCore();
$project->bootAddons();

// use original error handlers of the tools
ErrorHandler::unregister();

return $project;
