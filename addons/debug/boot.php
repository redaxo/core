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
use Redaxo\Debug\ApiFunction\ClockworkMetadata;
use Redaxo\Debug\DebugAddon;
use Redaxo\Debug\DebugApiFunction;
use Redaxo\Debug\DebugExtension;
use Redaxo\Debug\DebugLogger;
use Redaxo\Debug\DebugSql;
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

$addon = DebugAddon::instance();

Sql::setFactoryClass(DebugSql::class);
Extension::setFactoryClass(DebugExtension::class);

Logger::setFactoryClass(DebugLogger::class);
ApiFunction::setFactoryClass(DebugApiFunction::class);

Response::setHeader('X-Clockwork-Id', Type::string($addon->clockworkRequest->id));
Response::setHeader('X-Clockwork-Version', Clockwork::VERSION);

Response::setHeader('X-Clockwork-Path', ClockworkMetadata::getUrl());

$shutdownFn = static function () use ($addon) {
    $clockwork = $addon->clockwork;
    $req = $addon->clockworkRequest;

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
        'Extension Points' => count(DebugExtension::$extensionPoints),
        'Registered Extensions' => count(DebugExtension::$extensions),
    ]);

    $ep->table('Executed Extension Points', DebugExtension::$extensionPoints);
    $ep->table('Registered Extensions', DebugExtension::$extensions);
};

if ('cli' === PHP_SAPI) {
    Extension::register(ConsoleShutdown::NAME, static function (ConsoleShutdown $extensionPoint) use ($addon, $shutdownFn) {
        $shutdownFn();

        $command = $extensionPoint->command;
        $input = $extensionPoint->input;
        $output = $extensionPoint->output;
        $exitCode = $extensionPoint->exitCode;

        // we need to make sure that the storage path exists after actions like cache:clear
        $addon->ensureClockworkStoragePath();

        $clockwork = $addon->clockwork;
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
    register_shutdown_function(static function () use ($addon, $shutdownFn) {
        // don't track preflight requests
        if (in_array($_SERVER['REQUEST_URI'] ?? null, ['/__clockwork/latest', '/assets/addons/debug/clockwork/manifest.json'], true)) {
            return;
        }

        $shutdownFn();

        // we need to make sure that the storage path exists after actions like cache:clear
        $addon->ensureClockworkStoragePath();

        $clockwork = $addon->clockwork;
        $clockwork->resolveRequest();
        $clockwork->storeRequest();
    });
}
