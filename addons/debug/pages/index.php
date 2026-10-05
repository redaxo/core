<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Type;

use function Redaxo\Core\View\escape;

$index = Type::string(file_get_contents(Addon::require('debug')->getAssetsPath('clockwork/index.html')));

$editor = Editor::factory();
$curEditor = $editor->getName();
$editorBasepath = $editor->getBasepath();

$siteKey = rex_debug_clockwork::getFullClockworkApiUrl();
$localPath = null;
$realPath = null;

if ($editorBasepath) {
    $localPath = escape($editorBasepath, 'js');
    $realPath = escape(Path::base(), 'js');
}

// prepend backend folder
$apiUrl = dirname(Type::string($_SERVER['REQUEST_URI'] ?? null)) . '/' . rex_debug_clockwork::getClockworkApiUrl();
$appearance = Appearance::getTheme();
if (!$appearance) {
    $appearance = 'auto';
}

$nonce = Response::getNonce();

$injectedScript = <<<EOF
    <script nonce="$nonce">
        let store;
        try {
            store = JSON.parse(localStorage.getItem('clockwork'));
        } catch (e) {
            store = {};
        }

        if (!store) store = {};
        if (!store.settings) store.settings = {};
        if (!store.settings.global) store.settings.global = {};

        store.settings.global.editor = '$curEditor';
        store.settings.global.metadataPath = '$apiUrl';
        store.settings.global.appearance = '$appearance';

        if (!store.settings.site) store.settings.site = {};

        store.settings.site['$siteKey'] = {localPathMap: {local: "$localPath", real: "$realPath"}};

        localStorage.setItem('clockwork', JSON.stringify(store))
    </script>
    EOF;

echo str_replace('<body>', '<body>' . $injectedScript, $index);
