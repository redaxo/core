<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function is_array() with array will always evaluate to true.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/sql/util.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
