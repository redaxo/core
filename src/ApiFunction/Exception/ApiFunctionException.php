<?php

namespace Redaxo\Core\ApiFunction\Exception;

use Redaxo\Core\Exception\Exception;
use Throwable;

/**
 * Exception-Type to indicate exceptions in an api function.
 * The messages of this exception will be displayed to the end-user.
 *
 * @see ApiFunction
 */
class ApiFunctionException extends \Exception implements Exception
{
    /** @psalm-taint-sink html $message */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
