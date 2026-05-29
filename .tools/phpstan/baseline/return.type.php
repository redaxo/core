<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method Redaxo\\Core\\Security\\Login::getCookieParams() should return array{lifetime: int|null, path: string|null, domain: string|null, secure: bool|null, httponly: bool|null, samesite: string|null} but returns array{lifetime: mixed, path: mixed, domain: mixed, secure: mixed, httponly: mixed, samesite: mixed, ...<mixed>}.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Security/Login.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
