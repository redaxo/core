<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Util\Type;

$addon = Addon::require('debug');

// extract clockwork frontend
$zipArchive = new ZipArchive();

$path = __DIR__ . '/frontend/frontend.zip';

$error = 'Unable to extract the Clockwork frontend archive ' . $path;
try {
    $extracted = true === $zipArchive->open($path) && $zipArchive->extractTo($addon->getAssetsPath('clockwork'));
} catch (Exception $e) {
    throw new UserMessageException($error . ': ' . $e->getMessage(), $e);
}

if (!$extracted) {
    throw new UserMessageException($error);
}

$zipArchive->close();

$indexPath = $addon->getAssetsPath('clockwork/index.html');

$index = Type::string(file_get_contents($indexPath));
$index = Type::string(preg_replace('/(href|src)=("?)([^>\s]+)/', '$1=$2' . $addon->getAssetsUrl('clockwork/$3'), $index));
file_put_contents($indexPath, $index);
