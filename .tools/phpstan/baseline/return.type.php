<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_install_packages::getAddPackages() should return array<string, array{name: string, author: string, shortdescription: string, description: string, website: string, counter: int, created: string, updated: string, ...}> but returns array<string, array{name: string, author: string, shortdescription: string, description: string, website: string, counter: int, created: string, updated: string, ...}>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/install/lib/packages.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_login::getCookieParams() should return array{lifetime: int|null, path: string|null, domain: string|null, secure: bool|null, httponly: bool|null, samesite: string|null} but returns array{lifetime: mixed, path: mixed, domain: mixed, secure: mixed, httponly: mixed, samesite: mixed, ...<mixed>}.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/login/login.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_package::splitId() should return array{string, string|null} but returns non-empty-list<string|null>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/packages/package.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_socket::factory() should return static(rex_socket) but returns rex_socket_proxy.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/util/socket/socket.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
