<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

$finder = Finder::create()
    ->in([
        __DIR__ . '/.tools',
        __DIR__ . '/addons',
        __DIR__ . '/boot',
        __DIR__ . '/fragments',
        __DIR__ . '/pages',
        __DIR__ . '/project',
        __DIR__ . '/setup',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'var',
        'vendor',
    ])
    ->append([
        __FILE__,
        __DIR__ . '/project/bin/console',
        __DIR__ . '/rector.php',
    ])
;

return Config::redaxo6()
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.tools/.cache/php-cs-fixer.cache')
;
