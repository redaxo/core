<?php

use Project\Project;

require_once dirname(__DIR__, 4) . '/vendor/autoload_runtime.php';

return static fn (array $context) => new Project('backend');
