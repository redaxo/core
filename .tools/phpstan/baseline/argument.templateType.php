<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Unable to resolve the template type T in call to function Redaxo\\Core\\View\\escape',
    'count' => 1,
    'path' => __DIR__ . '/../../../pages/system/log.external.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
