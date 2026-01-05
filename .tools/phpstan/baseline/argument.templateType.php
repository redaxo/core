<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type T in call to function Redaxo\\Core\\View\\escape',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/pages/system.log.external.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type TInstance in call to static method Redaxo\\Core\\Tests\\Base\\TestInstanceListPool::getInstanceList()',
    'count' => 1,
    'path' => __DIR__ . '/../../../tests/Base/InstanceListPoolTraitTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
