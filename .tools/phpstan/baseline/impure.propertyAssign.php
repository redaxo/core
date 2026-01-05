<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Impure property assignment in pure function Redaxo\\Core\\View\\escape().',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/View/escape.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
