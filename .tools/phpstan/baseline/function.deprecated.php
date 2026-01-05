<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function ini_set() with deprecated option \'session.sid_bits_per_character\'.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Security/Login.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Call to function ini_set() with deprecated option \'session.sid_length\'.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Security/Login.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
