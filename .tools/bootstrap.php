<?php

use Project\Project;
use Redaxo\Core\Core;
use Redaxo\Core\Environment;
use Redaxo\Core\ErrorHandler;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$project = new Project(Environment::Console);

$project->bootCore();

// Run tests in debug mode so caches that key off it (e.g. class discovery) self-invalidate on file changes,
// ensuring attribute discovery always reflects the current sources (such as newly added test fixtures).
Core::setProperty('debug', ['enabled' => true]);

$project->bootAddons();

// use original error handlers of the tools
ErrorHandler::unregister();

return $project;
