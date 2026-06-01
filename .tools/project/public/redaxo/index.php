<?php

use Project\Project;
use Redaxo\Core\Environment;

require_once dirname(__DIR__, 4) . '/vendor/autoload_runtime.php';

return static fn (array $context) => new Project(Environment::Backend);
