<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Right side of && is always false.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Form/Select/CategorySelect.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
