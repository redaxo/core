<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method Redaxo\\Core\\Security\\User::getComplexPerm() should return Redaxo\\Core\\Content\\ModulePermission|Redaxo\\Core\\Content\\StructurePermission|Redaxo\\Core\\Language\\LanguagePermission|Redaxo\\Core\\MediaPool\\MediaPoolPermission|null but returns Redaxo\\Core\\Security\\ComplexPermission|null.',
    'count' => 2,
    'path' => __DIR__ . '/../../../src/Security/User.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
