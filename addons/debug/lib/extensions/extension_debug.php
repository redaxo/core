<?php

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Timer;

/**
 * @internal
 */
class rex_extension_debug extends Extension
{
    private static array $extensionPoints = [];
    private static array $extensions = [];
    private static array $listeners = [];

    public static function registerPoint(ExtensionPoint $extensionPoint)
    {
        $coreTimer = Core::getProperty('timer');
        $absDur = $coreTimer->getDelta();

        $timer = new Timer();
        $epStart = microtime(true);
        $res = parent::registerPoint($extensionPoint);
        $epEnd = microtime(true);
        $epDur = $timer->getDelta();

        $memory = Formatter::bytes(memory_get_usage(true), [3]);

        self::$extensionPoints[] = [
            '#' => count(self::$extensionPoints),
            'ep' => $extensionPoint->getName(),
            'subject' => $extensionPoint->getSubject(),
            'params' => $extensionPoint->getParams() ?: '',
            'read_only' => $extensionPoint->isReadonly(),
            'started at (ms)' => $absDur,
            'duration (ms)' => $epDur,
            'memory' => $memory,
            'result' => $res,
        ];

        $data = rex_debug::getTrace([Extension::class]);
        $data['listeners'] = self::$listeners[$extensionPoint->getName()] ?? [];

        rex_debug_clockwork::getInstance()
            ->event('EP: ' . $extensionPoint->getName(), [
                'subject' => $extensionPoint->getSubject(),
                'params' => $extensionPoint->getParams(),
                'result' => $res,
                'start' => $epStart,
                'end' => $epEnd,
                'data' => $data,
            ]);

        return $res;
    }

    public static function register($extensionPoint, callable $extension, $level = self::NORMAL, array $params = [])
    {
        parent::register($extensionPoint, $extension, $level, $params);

        $trace = rex_debug::getTrace([Extension::class]);
        if (!is_array($extensionPoint)) {
            $extensionPoint = [$extensionPoint];
        }

        foreach ($extensionPoint as $ep) {
            self::$listeners[$ep][] = $trace['file'] . ':' . $trace['line'];

            self::$extensions[] = [
                '#' => count(self::$extensions),
                'name' => $ep,
                'file' => $trace['file'],
                'line' => $trace['line'],
            ];
        }
    }

    public static function getExtensionPoints(): array
    {
        return self::$extensionPoints;
    }

    public static function getExtensions(): array
    {
        return self::$extensions;
    }
}
