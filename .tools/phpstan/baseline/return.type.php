<?php

declare(strict_types=1);

// total 3 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_user::getComplexPerm() should return rex_clang_perm|rex_media_perm|rex_module_perm|rex_structure_perm|null but returns rex_complex_perm|null.',
    'count' => 2,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/login/user.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_socket::factory() should return static(rex_socket) but returns rex_socket_proxy.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/util/socket/socket.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
