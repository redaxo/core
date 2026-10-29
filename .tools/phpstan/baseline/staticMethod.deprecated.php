<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Call to deprecated method requireBootstrapCompat() of class Redaxo\\Core\\View\\Asset:
since 6.0, move the markup to the REDAXO components; the styles go away with REDAXO 7',
    'count' => 4,
    'path' => __DIR__ . '/../../../tests/View/AssetTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
