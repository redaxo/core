<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Impure property assignment in pure function Redaxo\\Core\\View\\escape().',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/View/escape.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
