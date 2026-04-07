<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Translation\I18n;

$addon = Addon::require('debug');

// the filenames contain rev hashes and the old ones would never be cleaned up
Dir::delete($addon->getAssetsPath());

// extract clockwork frontend
$zipArchive = new ZipArchive();

// use path relative to __DIR__ to get correct path in update temp dir
$path = __DIR__ . '/frontend/frontend.zip';

$message = '';
try {
    if (
        true === $zipArchive->open($path)
        && $zipArchive->extractTo($addon->getAssetsPath('clockwork'))
    ) {
        $zipArchive->close();

        $indexPath = $addon->getAssetsPath('clockwork/index.html');

        $index = file_get_contents($indexPath);
        $index = preg_replace('/(href|src)=("?)([^>\s]+)/', '$1=$2' . $addon->getAssetsUrl('clockwork/$3'), $index);
        file_put_contents($indexPath, $index);
    } else {
        $message = I18n::msg('debug_error_unzip') . '<br>' . $path;
    }
} catch (Exception $e) {
    $message = I18n::msg('debug_error_unzip') . '<br>' . $path;
    $message .= '<br>' . $e->getMessage();
}

if ('' != $message) {
    throw new UserMessageException($message);
}
