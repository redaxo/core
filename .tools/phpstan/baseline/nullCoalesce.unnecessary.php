<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Coalesce operator ?? is unnecessary because the left side is always set and the right side is null.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/MediaPool/MediaHandler.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
