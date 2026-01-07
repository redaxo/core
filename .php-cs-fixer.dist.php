<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

$src = __DIR__ . '/redaxo/src';
$tools = __DIR__ . '/.tools';

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        $src . '/core',
        $src . '/addons/debug',
        $src . '/addons/install',
        $tools,
    ])
    ->append([
        __FILE__,
        __DIR__ . '/rector.php',
        $tools . '/bin/clone-addon',
        $tools . '/bin/console',
        $tools . '/bin/reinstall-core',
        $tools . '/bin/release',
        $tools . '/bin/update-root-composer',
    ])
;

return Config::redaxo6()
    ->setFinder($finder)
;
