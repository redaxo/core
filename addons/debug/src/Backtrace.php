<?php

use Redaxo\Core\Database\Sql;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Log\Logger;

/**
 * @internal
 */
final class rex_debug
{
    /** @var list<class-string> */
    private static array $ignoreClasses = [
        rex_extension_debug::class,
        rex_api_function_debug::class,
        self::class,
        rex_api_debug::class,
        rex_logger_debug::class,
        rex_sql_debug::class,
        Sql::class,
        Logger::class,
        ErrorHandler::class,
    ];

    /**
     * @param list<class-string> $ignoredClasses
     * @return array{file: string|null, line: int|null, trace: list<array{function: string, line?: int, file?: string, class?: class-string, type?: string, args?: list<mixed>, object?: object}>}
     */
    public static function getTrace(array $ignoredClasses = []): array
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
