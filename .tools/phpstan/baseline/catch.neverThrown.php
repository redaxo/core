<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Dead catch - Throwable is never thrown in the try block.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/util/timer_test.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
