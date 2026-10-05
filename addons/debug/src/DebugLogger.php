<?php

namespace Redaxo\Debug;

use Clockwork\Helpers\StackTrace;
use Redaxo\Core\Log\Logger;
use Stringable;

use function is_int;

/**
 * @internal
 */
final class DebugLogger extends Logger
{
    public function log($level, string|Stringable $message, array $context = [], ?string $file = null, ?int $line = null, ?string $url = null): void
    {
        /** @var mixed $levelType */
        $levelType = is_int($level) ? self::getLogLevel($level) : $level;

        /** @var StackTrace $trace */
        $trace = StackTrace::from(Backtrace::capture()['trace']);
        DebugAddon::instance()->clockwork->log($levelType, $message, ['trace' => $trace]);

        parent::log($level, $message, $context, $file, $line);
    }
}
