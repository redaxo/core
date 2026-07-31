<?php

namespace Redaxo\Core\Log;

use ErrorException;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Core;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Exception\HttpException;
use Redaxo\Core\Security\BackendLogin;
use Stringable;
use Throwable;

use function function_exists;

use const E_COMPILE_WARNING;
use const E_DEPRECATED;
use const E_NOTICE;
use const E_USER_DEPRECATED;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

/**
 * Simple Logger class.
 *
 * @psalm-consistent-constructor
 */
class Logger extends AbstractLogger
{
    use FactoryTrait;

    private static ?LogFile $file = null;

    public static function factory(): static
    {
        $class = self::getFactoryClass();
        return new $class();
    }

    /** Returns the path to the system.log file. */
    public static function getPath(): string
    {
        return Path::log('system.log');
    }

    /** Shorthand: Logs the given Exception. */
    public static function logException(Throwable $exception, ?string $url = null): void
    {
        if ($exception instanceof ErrorException) {
            self::logError($exception->getSeverity(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $url);

            return;
        }

        if ($exception instanceof HttpException) {
            // Client errors should not be logged to system error log (if not dev mode or backend admin).
            // This prevents that external website visitors can fill up the log (and possibly trigger error emails etc.).
            if (!Core::isDevMode() && $exception->isClientError() && (!($user = BackendLogin::createUser()) || !$user->admin)) {
                return;
            }

            $exception = $exception->getPrevious() ?? $exception; // log original exception
        }

        $logger = self::factory();
        $logger->log($exception::class, $exception->getMessage(), [], $exception->getFile(), $exception->getLine(), $url);
    }

    /**
     * Shorthand: Logs a error message.
     *
     * @param int $errno The error code to log, e.g. E_WARNING
     * @param string $errstr The error message
     * @param string $errfile The file in which the error occured
     * @param int $errline The line of the file in which the error occured
     */
    public static function logError(int $errno, string $errstr, string $errfile, int $errline, ?string $url = null): void
    {
        $logger = self::factory();
        $logger->log(ErrorHandler::getErrorType($errno), $errstr, [], $errfile, $errline, $url);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level either one of LogLevel::* or also any other string
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = [], ?string $file = null, ?int $line = null, ?string $url = null): void
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::log($level, $message, $context, $file, $line);
            return;
        }

        $message = (string) $message;

        self::open();
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        $message = strtr($message, $replace);

        if (!str_starts_with($level, 'rex_')) {
            $level = ucfirst($level);
        }

        $logData = [$level, $message];
        if ($file && $line || $url) {
            $logData[] = $file ? Path::relative($file) : '';
            $logData[] = $line ?? '';
            if ($url) {
                $logData[] = $url;
            }
        }
        self::$file->add($logData);

        // forward the error into phps' error log if error_log function is not disabled
        if (function_exists('error_log')) {
            error_log($message, 0);
        }
    }

    /**
     * Prepares the logifle for later use.
     *
     * @psalm-assert !null self::$file
     */
    public static function open(): void
    {
        // check if already opened
        self::$file ??= LogFile::factory(self::getPath(), 2_000_000);
    }

    /**
     * Closes the logfile. The logfile is not be able to log further message after beeing closed.
     *
     * You dont need to close the logfile manually when it was registered during the request.
     */
    public static function close(): void
    {
        self::$file = null;
    }

    /**
     * Map php error codes to PSR3 error levels.
     *
     * @param int $errno a php error code, e.g. E_ERROR
     */
    public static function getLogLevel(int $errno): string
    {
        return match ($errno) {
            E_USER_DEPRECATED, E_DEPRECATED, E_USER_WARNING, E_WARNING, E_COMPILE_WARNING => LogLevel::WARNING,
            E_USER_NOTICE, E_NOTICE => LogLevel::NOTICE,
            default => LogLevel::ERROR,
        };
    }
}
