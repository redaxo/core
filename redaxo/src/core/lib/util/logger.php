<?php

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Simple Logger class.
 *
 * @author staabm
 *
 * @package redaxo\core
 */
class rex_logger extends AbstractLogger
{
    use rex_factory_trait;

    /** @var rex_log_file|null */
    private static $file;

    /**
     * Returns the path to the system.log file.
     *
     * @return string
     */
    public static function getPath()
    {
        return rex_path::log('system.log');
    }

    /**
     * Shorthand: Logs the given Exception.
     *
     * @param Throwable|Exception $exception The Exception to log
     * @return void
     */
    public static function logException($exception, ?string $url = null)
    {
        if ($exception instanceof ErrorException) {
            self::logError($exception->getSeverity(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $url);

            return;
        }

        if ($exception instanceof rex_http_exception) {
            // Client errors should not be logged to system error log (if not debug mode or backend admin).
            // This prevents that external website visitors can fill up the log (and possibly trigger error emails etc.).
            if (!rex::isDebugMode() && $exception->isClientError() && (!($user = rex_backend_login::createUser()) || !$user->isAdmin())) {
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
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public static function logError($errno, $errstr, $errfile, $errline, ?string $url = null)
    {
        if (!is_int($errno)) {
            throw new InvalidArgumentException('Expecting $errno to be integer, but ' . gettype($errno) . ' given!');
        }
        if (!is_string($errstr)) {
            throw new InvalidArgumentException('Expecting $errstr to be string, but ' . gettype($errstr) . ' given!');
        }
        if (!is_string($errfile)) {
            throw new InvalidArgumentException('Expecting $errfile to be string, but ' . gettype($errfile) . ' given!');
        }
        if (!is_int($errline)) {
            throw new InvalidArgumentException('Expecting $errline to be integer, but ' . gettype($errline) . ' given!');
        }

        $logger = self::factory();
        $logger->log(rex_error_handler::getErrorType($errno), $errstr, [], $errfile, $errline, $url);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level either one of LogLevel::* or also any other string
     * @param string|Stringable $message
     * @param array<mixed> $context
     * @param string|null $file
     * @param int|null $line
     *
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = [], $file = null, $line = null, ?string $url = null): void
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::log($level, $message, $context, $file, $line);
            return;
        }

        if ($message instanceof Stringable) {
            $message = (string) $message;
        } elseif (!is_string($message)) {
            throw new InvalidArgumentException('Expecting $message to be string or \Stringable, but ' . gettype($message) . ' given!');
        }

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
            $logData[] = $file ? rex_path::relative($file) : '';
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
     * @return void
     */
    public static function open()
    {
        // check if already opened
        if (!self::$file) {
            self::$file = rex_log_file::factory(self::getPath(), 2_000_000);
        }
    }

    /**
     * Closes the logfile. The logfile is not be able to log further message after beeing closed.
     *
     * You dont need to close the logfile manually when it was registered during the request.
     * @return void
     */
    public static function close()
    {
        self::$file = null;
    }

    /**
     * Map php error codes to PSR3 error levels.
     *
     * @param int $errno a php error code, e.g. E_ERROR
     *
     * @return string
     */
    public static function getLogLevel($errno)
    {
        return match ($errno) {
            E_USER_DEPRECATED, E_DEPRECATED, E_USER_WARNING, E_WARNING, E_COMPILE_WARNING => LogLevel::WARNING,
            E_USER_NOTICE, E_NOTICE => LogLevel::NOTICE,
            default => LogLevel::ERROR,
        };
    }

    /** @return self */
    public static function factory()
    {
        $class = self::getFactoryClass();
        return new $class();
    }
}
