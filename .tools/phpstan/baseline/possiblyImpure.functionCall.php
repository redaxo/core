<?php

declare(strict_types=1);

// total 8 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function str_replace() in pure method Redaxo\\Core\\Database\\Sql::escapeLikeWildcards().',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/Database/Sql.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_match() in pure function Redaxo\\Core\\View\\escape().',
    'count' => 3,
    'path' => __DIR__ . '/../../../src/View/escape.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_replace() in pure function Redaxo\\Core\\View\\escape().',
    'count' => 1,
    'path' => __DIR__ . '/../../../src/View/escape.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Possibly impure call to function preg_replace_callback() in pure function Redaxo\\Core\\View\\escape().',
    'count' => 3,
    'path' => __DIR__ . '/../../../src/View/escape.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
