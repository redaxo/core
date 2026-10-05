<?php

namespace Redaxo\Debug;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Log\Logger;
use Redaxo\Debug\ApiFunction\ClockworkMetadata;

use function array_slice;
use function count;
use function in_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @internal
 */
final class Backtrace
{
    /** @var list<class-string> */
    private static array $ignoreClasses = [
        DebugExtension::class,
        DebugApiFunction::class,
        self::class,
        ClockworkMetadata::class,
        DebugLogger::class,
        DebugSql::class,
        Sql::class,
        Logger::class,
        ErrorHandler::class,
    ];

    /**
     * @param list<class-string> $ignoredClasses
     * @return array{file: string|null, line: int|null, trace: list<array{function: string, line?: int, file?: string, class?: class-string, type?: string, args?: list<mixed>, object?: object}>}
     */
    public static function capture(array $ignoredClasses = []): array
    {
        $ignoredClasses = array_merge(self::$ignoreClasses, $ignoredClasses);
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $start = 0;
        for ($i = 0; $i < count($trace); ++$i) {
            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            if (isset($trace[$i + 1]['class']) && in_array($trace[$i + 1]['class'], $ignoredClasses, true)) {
                continue;
            }

            $start = $i;
            break;
        }
        return [
            'file' => $trace[$start]['file'] ?? null,
            'line' => $trace[$start]['line'] ?? null,
            'trace' => array_slice($trace, $start),
        ];
    }
}
