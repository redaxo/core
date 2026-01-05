<?php

declare(strict_types=1);

// total 1 error

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Unreachable statement - code above always terminates.',
    'count' => 1,
    'path' => __DIR__ . '/../../../tests/Util/TimerTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
