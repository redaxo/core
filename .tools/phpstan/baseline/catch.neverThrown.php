<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Dead catch - Throwable is never thrown in the try block.',
    'count' => 1,
    'path' => __DIR__ . '/../../../tests/Util/TimerTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
