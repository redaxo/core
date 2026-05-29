<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type T in call to function Redaxo\\Core\\View\\escape',
    'count' => 1,
    'path' => __DIR__ . '/../../../pages/system/log.external.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type TInstance in call to static method Redaxo\\Core\\Tests\\Base\\TestInstanceListPool::getInstanceList()',
    'count' => 1,
    'path' => __DIR__ . '/../../../tests/Base/InstanceListPoolTraitTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
