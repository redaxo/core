<?php

declare(strict_types=1);

// total 4 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $replace of function str_replace expects array<string>|string, list<int> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Content/ArticleAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $replace of function str_replace expects array<string>|string, array<int, int|string> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Content/ArticleContentBase.php',
];
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
