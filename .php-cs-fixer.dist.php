<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

$src = __DIR__ . '/redaxo/src';
$bootPath = realpath($src . '/core/boot.php');
$tools = __DIR__ . '/.tools';

$finder = Finder::create()
    ->in([
        $src . '/core',
        $src . '/addons/backup',
        $src . '/addons/be_style',
        $src . '/addons/cronjob',
        $src . '/addons/debug',
        $src . '/addons/install',
        $src . '/addons/media_manager',
        $src . '/addons/mediapool',
        $src . '/addons/metainfo',
        $src . '/addons/phpmailer',
        $src . '/addons/project',
        $src . '/addons/structure',
        $src . '/addons/users',
        $tools,
    ])
    ->append([
        __FILE__,
        __DIR__ . '/rector.php',
        $tools . '/bin/clone-addon',
        $tools . '/bin/reinstall-core',
        $tools . '/bin/release',
        $tools . '/bin/update-root-composer',
    ])
    ->filter(static function (SplFileInfo $file) use ($bootPath) {
        return $bootPath !== $file->getRealPath();
    })
;

return Config::redaxo5()
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.tools/.cache/php-cs-fixer.cache')
;
