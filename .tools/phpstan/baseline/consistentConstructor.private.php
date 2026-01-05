<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Private constructor cannot be enforced as consistent for child classes.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Log/LogFile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Private constructor cannot be enforced as consistent for child classes.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Security/CsrfToken.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
