<?php
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<!doctype html>
<html lang="<?= I18n::msg('htmllang') ?>">
<head>
    <meta charset="utf-8" />

    <title><?= $this->pageTitle ?></title>

    <meta name="viewport" content="width=device-width, initial-scale=1" />
<?php
    $user = Core::getUser();

    $colorScheme = 'light dark'; // default: support both
    if (Core::getProperty('theme')) {
        // global theme from config.yml
        $colorScheme = escape((string) Core::getProperty('theme'));
    } elseif ($user && $user->getValue('theme')) {
        // user selected theme
        $colorScheme = escape($user->getValue('theme'));
    }
    echo "\n" . '    <meta name="color-scheme" content="' . $colorScheme . '">';
    echo "\n" . '    <style nonce="' . Response::getNonce() . '">:root { color-scheme: ' . $colorScheme . ' }</style>';

    $assetDir = Path::assets();

    foreach ($this->cssFiles as $media => $files) {
        foreach ($files as $file) {
            $file = (string) $file;
            $path = Path::frontend(Path::absolute($file));
            if (!Core::isDebugMode() && str_starts_with($path, $assetDir) && $mtime = @filemtime($path)) {
                $file = Url::backendController(['asset' => ltrim($file, '.'), 'buster' => $mtime]);
            } elseif ($mtime = @filemtime($path)) {
                $file .= '?buster=' . $mtime;
            }
            echo "\n" . '    <link rel="stylesheet" type="text/css" media="' . $media . '" href="' . $file . '" />';
        }
    }
    echo "\n";
    echo "\n" . '    <script type="text/javascript" nonce="' . Response::getNonce() . '">';
    echo "\n" . '    <!--';
    echo "\n" . '    var rex = ' . $this->jsProperties . ';';
    echo "\n" . '    //-->';
    echo "\n" . '    </script>';
    foreach ($this->jsFiles as $file) {
        if (is_string($file)) {
            // BC Case
            $options = [];
        } else {
            [$file, $options] = $file;
        }

        $file = (string) $file;
        $path = Path::frontend(Path::absolute($file));
        if (array_key_exists(Asset::JS_IMMUTABLE, $options) && $options[Asset::JS_IMMUTABLE]) {
            if (!Core::isDebugMode() && str_starts_with($path, $assetDir) && $mtime = @filemtime($path)) {
                $file = Url::backendController(['asset' => ltrim($file, '.'), 'buster' => $mtime]);
            }
        } elseif ($mtime = @filemtime($path)) {
            $file .= '?buster=' . $mtime;
        }

        $attributes = [];
        if (array_key_exists(Asset::JS_ASYNC, $options) && $options[Asset::JS_ASYNC]) {
            $attributes[] = 'async="async"';
        }
        if (array_key_exists(Asset::JS_DEFERED, $options) && $options[Asset::JS_DEFERED]) {
            $attributes[] = 'defer="defer"';
        }

        echo "\n" . '    <script type="text/javascript" src="' . $file . '" ' . implode(' ', $attributes) . ' nonce="' . Response::getNonce() . '"></script>';
    }
?>

    <?= $this->favicon ? '<link rel="shortcut icon" href="' . $this->favicon . '" />' : '' ?>

    <link rel="apple-touch-icon" sizes="180x180" href="<?= Url::coreAssets('icons/apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::coreAssets('icons/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= Url::coreAssets('icons/favicon-16x16.png') ?>">
    <link rel="manifest" href="<?= Url::coreAssets('icons/site.webmanifest') ?>">
    <link rel="mask-icon" href="<?= Url::coreAssets('icons/safari-pinned-tab.svg') ?>" color="<?= escape((string) Core::getConfig('be_style_labelcolor', '#4d99d3')) ?>">
    <meta name="msapplication-TileColor" content="#2d89ef">

    <?= $this->pageHeader ?>

</head>
<body<?= $this->bodyAttr ?>>

<div class="rex-ajax-loader" id="rex-js-ajax-loader">
    <div class="rex-ajax-loader-element"></div>
    <div class="rex-ajax-loader-backdrop"></div>
</div>

<div id="rex-start-of-page" class="rex-page">
