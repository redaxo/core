<?php

declare(strict_types=1);

// total 8 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_match() in pure function rex_escape().',
    'count' => 3,
    'path' => __DIR__ . '/../../../redaxo/src/core/functions/function_rex_escape.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_replace() in pure function rex_escape().',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/functions/function_rex_escape.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_replace_callback() in pure function rex_escape().',
    'count' => 3,
    'path' => __DIR__ . '/../../../redaxo/src/core/functions/function_rex_escape.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function str_replace() in pure method rex_sql::escapeLikeWildcards().',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/sql/sql.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
