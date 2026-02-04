<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'PHPDoc tag @var with type class-string is not subtype of native type lowercase-string&non-falsy-string.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/var/var.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
