<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function is_array() with array<non-falsy-string, int|non-falsy-string> will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/MediaPool/MediaHandler.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
