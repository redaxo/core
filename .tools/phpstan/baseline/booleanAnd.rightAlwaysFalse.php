<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Right side of && is always false.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/lib/select_category.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
