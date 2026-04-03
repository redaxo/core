<?php

declare(strict_types=1);

// total 2 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $priority of static method Redaxo\\Core\\MetaInfo\\MetaInfo::addField() expects int, string given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/MetaInfo/ApiFunction/DefaultFieldsCreate.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #5 $type of static method Redaxo\\Core\\MetaInfo\\MetaInfo::addField() expects int, string given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/MetaInfo/ApiFunction/DefaultFieldsCreate.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
