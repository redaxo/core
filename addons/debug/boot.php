<?php

use Clockwork\Clockwork;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Console\ExtensionPoint\ConsoleShutdown;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Timer;

use function Redaxo\Core\View\escape;

if (!rex_debug_clockwork::isRexDebugEnabled() || 'debug' === Request::get(ApiFunction::REQ_CALL_PARAM)) {
    return;
}

if (Core::isBackend() && 'debug' === Request::get('page') && Core::getUser()?->isAdmin()) {
    $index = file_get_contents(Addon::require('debug')->getAssetsPath('clockwork/index.html'));

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
    $apiUrl = dirname($_SERVER['REQUEST_URI']) . '/' . rex_debug_clockwork::getClockworkApiUrl();
    $appearance = Core::getTheme();
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

    $index = str_replace('<body>', '<body>' . $injectedScript, $index);
    Response::sendPage($index);
    exit;
}

Sql::setFactoryClass(rex_sql_debug::class);
Extension::setFactoryClass(rex_extension_debug::class);

Logger::setFactoryClass(rex_logger_debug::class);
ApiFunction::setFactoryClass(rex_api_function_debug::class);

Response::setHeader('X-Clockwork-Id', rex_debug_clockwork::getInstance()->getRequest()->id);
Response::setHeader('X-Clockwork-Version', Clockwork::VERSION);

Response::setHeader('X-Clockwork-Path', rex_debug_clockwork::getClockworkApiUrl());

$shutdownFn = static function () {
    $clockwork = rex_debug_clockwork::getInstance();

    $clockwork->timeline()->finalize($clockwork->getRequest()->time);

    foreach (Timer::$serverTimings as $label => $timings) {
        foreach ($timings['timings'] as $timing) {
            if ($timing['end'] - $timing['start'] >= 0.001) {
                $clockwork->timeline()->event($label, ['start' => $timing['start'], 'end' => $timing['end']]);
            }
        }
    }

    $req = $clockwork->getRequest();

    if (Core::isFrontend()) {
        $req->controller = 'article: ' . Article::getCurrentId() . '; clang: ' . Language::getCurrent()->getCode();
    } elseif (!Core::getConsole()) {
        $req->controller = 'page: ' . Controller::getCurrentPage();
    }

    foreach ($req->databaseQueries as $query) {
        match (Sql::getQueryType($query['query'])) {
            'SELECT' => $req->databaseSelects++,
            'INSERT' => $req->databaseInserts++,
            'UPDATE' => $req->databaseUpdates++,
            'DELETE' => $req->databaseDeletes++,
            default => $req->databaseOthers++,
        };
        if ($query['duration'] > 20) {
            ++$req->databaseSlowQueries;
        }
    }

    $ep = $clockwork->getRequest()->userData('ep');
    $ep->title('Extension Point');
    $ep->counters([
        'Extension Points' => count(rex_extension_debug::getExtensionPoints()),
        'Registered Extensions' => count(rex_extension_debug::getExtensions()),
    ]);

    $ep->table('Executed Extension Points', rex_extension_debug::getExtensionPoints());
    $ep->table('Registered Extensions', rex_extension_debug::getExtensions());
};

if ('cli' === PHP_SAPI) {
    Extension::register(ConsoleShutdown::NAME, static function (ConsoleShutdown $extensionPoint) use ($shutdownFn) {
        $shutdownFn();

        $command = $extensionPoint->getCommand();
        $input = $extensionPoint->getInput();
        // $output = $extensionPoint->getOutput();
        $exitCode = $extensionPoint->getExitCode();

        // we need to make sure that the storage path exists after actions like cache:clear
        rex_debug_clockwork::ensureStoragePath();

        $clockwork = rex_debug_clockwork::getInstance();
        $clockwork
            ->resolveAsCommand(
                $command->getName(),
                $exitCode,
                array_diff($input->getArguments(), $command->getDefinition()->getArgumentDefaults()),
                array_diff($input->getOptions(), $command->getDefinition()->getOptionDefaults()),
                $command->getDefinition()->getArgumentDefaults(),
                $command->getDefinition()->getOptionDefaults(),
                // $output->fetch()
            )
        ->storeRequest();
    });
} else {
    register_shutdown_function(static function () use ($shutdownFn) {
        // don't track preflight requests
        if (in_array($_SERVER['REQUEST_URI'], ['/__clockwork/latest', '/assets/addons/debug/clockwork/manifest.json'], true)) {
            return;
        }

        $shutdownFn();

        // we need to make sure that the storage path exists after actions like cache:clear
        rex_debug_clockwork::ensureStoragePath();

        $clockwork = rex_debug_clockwork::getInstance();
        $clockwork->resolveRequest()->storeRequest();
    });
}
