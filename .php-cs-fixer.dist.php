<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

$finder = Finder::create()
    ->in([
        __DIR__ . '/.tools',
        __DIR__ . '/boot',
        __DIR__ . '/fragments',
        __DIR__ . '/pages',
        __DIR__ . '/setup',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/redaxo/src/addons/debug',
        __DIR__ . '/redaxo/src/addons/install',
    ])
    ->append([
        __FILE__,
        __DIR__ . '/.tools/bin/clone-addon',
        __DIR__ . '/.tools/bin/console',
        __DIR__ . '/.tools/bin/reinstall-core',
        __DIR__ . '/.tools/bin/update-root-composer',
        __DIR__ . '/assets_src/vendor_files.php',
        __DIR__ . '/rector.php',
    ])
;

return Config::redaxo6()
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.tools/.cache/php-cs-fixer.cache')
;
