<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Return type (void) of method rex_metainfo_clang_handler::buildFilterCondition() should be compatible with return type (string) of method rex_metainfo_handler::buildFilterCondition()',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/clang_handler.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
