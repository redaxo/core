<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type T in call to function rex_escape',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/pages/system.log.external.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type T in call to static method rex_test_instance_list_pool::getInstanceList()',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_list_pool_trait_test.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
