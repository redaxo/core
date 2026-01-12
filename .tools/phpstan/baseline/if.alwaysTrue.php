<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'If condition is always true.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/response.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
