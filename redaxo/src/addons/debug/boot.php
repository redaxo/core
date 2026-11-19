<?php

use Clockwork\Clockwork;

if (!rex_debug_clockwork::isRexDebugEnabled() || 'debug' === rex_get(rex_api_function::REQ_CALL_PARAM)) {
    return;
}

if (rex::isBackend() && 'debug' === rex_request::get('page') && rex::getUser()?->isAdmin()) {
    $index = file_get_contents(rex_addon::get('debug')->getAssetsPath('clockwork/index.html'));

    $editor = rex_editor::factory();
    $curEditor = $editor->getName();
    $editorBasepath = $editor->getBasepath();

    $siteKey = rex_debug_clockwork::getFullClockworkApiUrl();
    $localPath = null;
    $realPath = null;

    if ($editorBasepath) {
        $localPath = rex_escape($editorBasepath, 'js');
        $realPath = rex_escape(rex_path::base(), 'js');
    }

    // prepend backend folder
    $apiUrl = dirname($_SERVER['REQUEST_URI']) . '/' . rex_debug_clockwork::getClockworkApiUrl();
    $appearance = rex::getTheme();
    if (!$appearance) {
        $appearance = 'auto';
    }

    $nonce = rex_response::getNonce();

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
    rex_response::sendPage($index);
    exit;
}

rex_sql::setFactoryClass(rex_sql_debug::class);
rex_extension::setFactoryClass(rex_extension_debug::class);

rex_logger::setFactoryClass(rex_logger_debug::class);
rex_api_function::setFactoryClass(rex_api_function_debug::class);

rex_response::setHeader('X-Clockwork-Id', rex_debug_clockwork::getInstance()->getRequest()->id);
rex_response::setHeader('X-Clockwork-Version', Clockwork::VERSION);

rex_response::setHeader('X-Clockwork-Path', rex_debug_clockwork::getClockworkApiUrl());

$shutdownFn = static function () {
    $clockwork = rex_debug_clockwork::getInstance();

    $clockwork->timeline()->finalize($clockwork->getRequest()->time);

    foreach (rex_timer::$serverTimings as $label => $timings) {
        foreach ($timings['timings'] as $timing) {
            if ($timing['end'] - $timing['start'] >= 0.001) {
                $clockwork->timeline()->event($label, ['start' => $timing['start'], 'end' => $timing['end']]);
            }
        }
    }

    $req = $clockwork->getRequest();

    if (rex::isBackend()) {
        $req->controller = 'page: ' . rex_be_controller::getCurrentPage();
    } elseif (rex_plugin::get('structure', 'content')->isAvailable()) {
        $req->controller = 'article: ' . rex_article::getCurrentId() . '; clang: ' . rex_clang::getCurrent()->getCode();
    }

    foreach ($req->databaseQueries as $query) {
        match (rex_sql::getQueryType($query['query'])) {
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
    rex_extension::register(rex_extension_point_console_shutdown::NAME, static function (rex_extension_point_console_shutdown $extensionPoint) use ($shutdownFn) {
        $shutdownFn();

        $command = $extensionPoint->getCommand();
        $input = $extensionPoint->getInput();
        // $output = $extensionPoint->getOutput();
        $exitCode = $extensionPoint->getExitCode();

        // we need to make sure that the storage path exists after actions like cache:clear
        rex_debug_clockwork::ensureStoragePath();

        $argumentDefaults = $command->getDefinition()->getArgumentDefaults();
        $optionDefaults = $command->getDefinition()->getOptionDefaults();

        $clockwork = rex_debug_clockwork::getInstance();
        $clockwork
            ->resolveAsCommand(
                $command->getName(),
                $exitCode,
                rex_debug_clockwork::getChangedInputValues($input->getArguments(), $argumentDefaults),
                rex_debug_clockwork::getChangedInputValues($input->getOptions(), $optionDefaults),
                $argumentDefaults,
                $optionDefaults,
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
