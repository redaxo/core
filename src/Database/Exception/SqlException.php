<?php

namespace Redaxo\Core\Database\Exception;

use PDOException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\Exception;
use Redaxo\Core\Exception\RuntimeException;
use Throwable;

class SqlException extends RuntimeException implements Exception
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
        final public readonly ?Sql $sql = null,
    ) {
        parent::__construct($message, $previous);
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
