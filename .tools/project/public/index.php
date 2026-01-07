<?php

use Project\Project;

require_once dirname(__DIR__, 3) . '/vendor/autoload_runtime.php';

return static fn (array $context) => new Project('frontend');
