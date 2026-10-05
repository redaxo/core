<?php

use Clockwork\Clockwork;
use Clockwork\Request\Timeline\Timeline;
use Clockwork\Request\UserData;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Console\ExtensionPoint\ConsoleShutdown;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Environment;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

if (!Core::isDevMode() || 'debug' === Request::get(ApiFunction::REQ_CALL_PARAM)) {
    return;
}

if (Core::isBackend()) {
    // the Clockwork frontend itself should not be profiled
    if ('debug' === Request::get('page')) {
        return;
    }

    Appearance::$devModeMarkerUrl = Url::backendPage('debug');
}

Sql::setFactoryClass(rex_sql_debug::class);
Extension::setFactoryClass(rex_extension_debug::class);

Logger::setFactoryClass(rex_logger_debug::class);
ApiFunction::setFactoryClass(rex_api_function_debug::class);

Response::setHeader('X-Clockwork-Id', Type::string(rex_debug_clockwork::getRequest()->id));
Response::setHeader('X-Clockwork-Version', Clockwork::VERSION);

Response::setHeader('X-Clockwork-Path', rex_debug_clockwork::getClockworkApiUrl());

$shutdownFn = static function () {
    $clockwork = rex_debug_clockwork::getInstance();
    $req = rex_debug_clockwork::getRequest();

    /** @var Timeline $timeline */
    $timeline = $clockwork->timeline();
    $timeline->finalize($req->time);

    foreach (Timer::$serverTimings as $label => $timings) {
        foreach ($timings['timings'] as $timing) {
            if ($timing['end'] - $timing['start'] >= 0.001) {
                $timeline->event($label, ['start' => $timing['start'], 'end' => $timing['end']]);
            }
        }
    }

    if (Core::isFrontend()) {
        $req->controller = 'article: ' . Article::getCurrentId() . '; language: ' . Language::getCurrent()->code;
    } elseif (Environment::Backend === Core::getEnvironment()) {
        $req->controller = 'page: ' . Controller::getCurrentPage();
    }

    /** @var array{query: string, duration: float} $query */
    foreach ($req->databaseQueries as $query) {
        /** @psalm-suppress MixedOperand */
        match (Sql::getQueryType($query['query'])) {
            'SELECT' => $req->databaseSelects++,
            'INSERT' => $req->databaseInserts++,
            'UPDATE' => $req->databaseUpdates++,
            'DELETE' => $req->databaseDeletes++,
            default => $req->databaseOthers++,
        };
        if ($query['duration'] > 20) {
            /** @psalm-suppress MixedOperand */
            ++$req->databaseSlowQueries;
        }
    }

    /** @var UserData $ep */
    $ep = $req->userData('ep');
    $ep->title('Extension Point');
    $ep->counters([
        'Extension Points' => count(rex_extension_debug::$extensionPoints),
        'Registered Extensions' => count(rex_extension_debug::$extensions),
    ]);

    $ep->table('Executed Extension Points', rex_extension_debug::$extensionPoints);
    $ep->table('Registered Extensions', rex_extension_debug::$extensions);
};

if ('cli' === PHP_SAPI) {
    Extension::register(ConsoleShutdown::NAME, static function (ConsoleShutdown $extensionPoint) use ($shutdownFn) {
        $shutdownFn();

        $command = $extensionPoint->command;
        $input = $extensionPoint->input;
        $output = $extensionPoint->output;
        $exitCode = $extensionPoint->exitCode;

        // we need to make sure that the storage path exists after actions like cache:clear
        rex_debug_clockwork::ensureStoragePath();

        $clockwork = rex_debug_clockwork::getInstance();
        $clockwork->resolveAsCommand(
            $command->getName(),
            $exitCode,
            array_diff($input->getArguments(), $command->getDefinition()->getArgumentDefaults()),
            array_diff($input->getOptions(), $command->getDefinition()->getOptionDefaults()),
            $command->getDefinition()->getArgumentDefaults(),
            $command->getDefinition()->getOptionDefaults(),
            // $output->fetch()
        );

        // a storage owned by another user (e.g. the web server) must not fail the command itself
        try {
            $clockwork->storeRequest();
        } catch (Exception $e) {
            $output = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $output->writeln('<comment>Clockwork could not store the command profile: ' . OutputFormatter::escape($e->getMessage()) . '</comment>');
        }
    });
} else {
    register_shutdown_function(static function () use ($shutdownFn) {
        // don't track preflight requests
        if (in_array($_SERVER['REQUEST_URI'] ?? null, ['/__clockwork/latest', '/assets/addons/debug/clockwork/manifest.json'], true)) {
            return;
        }

        $shutdownFn();

        // we need to make sure that the storage path exists after actions like cache:clear
        rex_debug_clockwork::ensureStoragePath();

        $clockwork = rex_debug_clockwork::getInstance();
        $clockwork->resolveRequest();
        $clockwork->storeRequest();
    });
}
