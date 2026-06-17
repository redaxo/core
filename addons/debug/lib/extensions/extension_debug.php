<?php

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Timer;

/**
 * @internal
 */
final class rex_extension_debug extends Extension
{
    /** @var list<array<string, mixed>> */
    public static array $extensionPoints = [];
    /** @var list<array<string, mixed>> */
    public static array $extensions = [];
    /** @var array<string, list<string>> */
    private static array $listeners = [];

    public static function dispatch(ExtensionPoint $extensionPoint): mixed
    {
        $coreTimer = Core::getProperty('timer');
        $absDur = $coreTimer->getDelta();

        $timer = new Timer();
        $epStart = microtime(true);
        $res = parent::dispatch($extensionPoint);
        $epEnd = microtime(true);
        $epDur = $timer->getDelta();

        $memory = Formatter::bytes(memory_get_usage(true), [3]);

        self::$extensionPoints[] = [
            '#' => count(self::$extensionPoints),
            'ep' => $extensionPoint->name,
            'subject' => $extensionPoint->subject,
            'params' => $extensionPoint->getParams() ?: '',
            'read_only' => $extensionPoint->readonly,
            'started at (ms)' => $absDur,
            'duration (ms)' => $epDur,
            'memory' => $memory,
            'result' => $res,
        ];

        $data = rex_debug::getTrace([Extension::class]);
        $data['listeners'] = self::$listeners[$extensionPoint->name] ?? [];

        rex_debug_clockwork::getInstance()
            ->event('EP: ' . $extensionPoint->name, [
                'subject' => $extensionPoint->subject,
                'params' => $extensionPoint->getParams(),
                'result' => $res,
                'start' => $epStart,
                'end' => $epEnd,
                'data' => $data,
            ]);

        return $res;
    }

    public static function register(string|array $extensionPoint, callable $extension, ExtensionLevel $level = ExtensionLevel::Normal): void
    {
        parent::register($extensionPoint, $extension, $level);

        $trace = rex_debug::getTrace([Extension::class]);
        if (!is_array($extensionPoint)) {
            $extensionPoint = [$extensionPoint];
        }

        foreach ($extensionPoint as $ep) {
            self::$listeners[$ep][] = ($trace['file'] ?? 'unknown') . (isset($trace['line']) ? ':' . $trace['line'] : '');

            self::$extensions[] = [
                '#' => count(self::$extensions),
                'name' => $ep,
                'file' => $trace['file'],
                'line' => $trace['line'],
            ];
        }
    }
}
