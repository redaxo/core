<?php

use Project\Project;
use Redaxo\Core\Environment;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Mode;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// this bootstrap does not run through symfony/runtime, so load the env vars ourselves
new Dotenv()->bootEnv(dirname(__DIR__) . '/project/.env');

// Run tests in dev mode so caches that key off it (e.g. class discovery) self-invalidate on file changes,
// ensuring attribute discovery always reflects the current sources (such as newly added test fixtures).
$_SERVER['REX_MODE'] = Mode::Dev->value;

$project = new Project(Environment::Console);

$project->bootCore();

$project->bootAddons();

// use original error handlers of the tools
ErrorHandler::unregister();

return $project;
