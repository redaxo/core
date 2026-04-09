<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_socket::factory() should return static(rex_socket) but returns rex_socket_proxy.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/util/socket/socket.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
