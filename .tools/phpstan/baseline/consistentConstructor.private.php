<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Private constructor cannot be enforced as consistent for child classes.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Log/LogFile.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
