<?php

namespace Redaxo\Core\Http\Exception;

use Redaxo\Core\Exception\Exception;
use Redaxo\Core\Exception\RuntimeException;
use Throwable;

use function is_string;

/**
 * Exception class for http-status code handling.
 */
class HttpException extends RuntimeException implements Exception
{
    public function __construct(
        string|Throwable $cause,
        final public readonly string $httpCode,
    ) {
        parent::__construct(is_string($cause) ? $cause : $cause->getMessage(), $cause instanceof Throwable ? $cause : null);
    }

    public function isClientError(): bool
    {
        return str_starts_with($this->httpCode, '4');
    }
}
