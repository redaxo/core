<?php

/**
 * @package redaxo\core
 */
class rex_exception extends Exception
{
    /** @param string $message */
    public function __construct($message, ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

/**
 * @package redaxo\core
 */
class rex_sql_exception extends rex_exception
{
    private ?rex_sql $sql;

    /** @param string $message */
    public function __construct($message, ?Exception $previous = null, ?rex_sql $sql = null)
    {
        parent::__construct($message, $previous);

        $this->sql = $sql;
    }

    /** @return rex_sql|null */
    public function getSql()
    {
        return $this->sql;
    }

    /** Returns the mysql native error code. */
    public function getErrorCode(): ?int
    {
        $previous = $this->getPrevious();
        if ($previous instanceof PDOException) {
            return $previous->errorInfo[1] ?? null;
        }
        return null;
    }
}

/**
 * Exception class when redaxo is unable to connect to the database.
 *
 * @package redaxo\core
 */
class rex_sql_could_not_connect_exception extends rex_sql_exception {}

/**
 * Exception class for user-friendly error messages.
 *
 * @package redaxo\core
 */
class rex_functional_exception extends rex_exception {}

/**
 * Exception class for http-status code handling.
 *
 * @package redaxo\core
 */
class rex_http_exception extends rex_exception
{
    /** @var string */
    private $httpCode;

    /** @param string $httpCode */
    public function __construct(Exception $cause, $httpCode)
    {
        parent::__construct($cause->getMessage(), $cause);
        $this->httpCode = $httpCode;
    }

    /** @return string */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function isClientError(): bool
    {
        return str_starts_with($this->httpCode, '4');
    }
}

/**
 * Exception class for yaml parse errors.
 *
 * @package redaxo\core
 */
class rex_yaml_parse_exception extends rex_exception {}
