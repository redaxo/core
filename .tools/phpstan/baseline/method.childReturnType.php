<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Return type (void) of method Redaxo\\Core\\MetaInfo\\Handler\\LanguageHandler::buildFilterCondition() should be compatible with return type (string) of method Redaxo\\Core\\MetaInfo\\Handler\\AbstractHandler::buildFilterCondition()',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/MetaInfo/Handler/LanguageHandler.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
