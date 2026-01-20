<?php

/**
 * This file is part of the ramsey/http-range library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace Ramsey\Http\Range\Exception;

use Throwable;

/**
 * Thrown to indicate the range given cannot be satisfied.
 */
class NotSatisfiableException extends HttpRangeException
{
    /**
     * Constructs a NotSatisfiableException.
     *
     * @param string $message The exception message.
     * @param string $range The range value parsed from the request.
     * @param float | int | string $totalSize The total size of the entity for which the range is requested.
     * @param int $code A custom error code, if applicable.
     * @param Throwable | null $previous A previous exception, if applicable.
     */
    public function __construct(
        string $message,
        private readonly string $range,
        private readonly float | int | string $totalSize,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the range that couldn't be satisfied.
     */
    public function getRange(): string
    {
        return $this->range;
    }

    /**
     * Returns the total size of the entity being requested.
     */
    public function getTotalSize(): float | int | string
    {
        return $this->totalSize;
    }
}
