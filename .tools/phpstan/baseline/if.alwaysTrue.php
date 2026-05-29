<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'If condition is always true.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Http/Response.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
