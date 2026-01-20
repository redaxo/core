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

namespace Ramsey\Http\Range;

use Psr\Http\Message\RequestInterface;
use Ramsey\Http\Range\Exception\NoRangeException;
use Ramsey\Http\Range\Unit\UnitInterface;

use function trim;

/**
 * `Range` represents an HTTP Range request header.
 *
 * For more information about range requests, see
 * [RFC 9110, section 14: Range Requests](https://www.rfc-editor.org/rfc/rfc9110.html#section-14).
 */
readonly class Range
{
    /**
     * Constructs an HTTP Range request header.
     *
     * @param RequestInterface $request A PSR-7-compatible HTTP request.
     * @param float | int | string $totalSize The total size of the entity for which a range is
     *     requested (this may be in bytes, items, etc.).
     * @param UnitFactoryInterface $unitFactory An optional factory to use for
     *     parsing range units.
     */
    public function __construct(
        private RequestInterface $request,
        private float | int | string $totalSize,
        private UnitFactoryInterface $unitFactory = new UnitFactory(),
    ) {
    }

    /**
     * Returns the PSR-7 HTTP request object.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Returns the total size of the entity for which the range is requested.
     */
    public function getTotalSize(): float | int | string
    {
        return $this->totalSize;
    }

    /**
     * Returns the unit factory used by this range.
     */
    public function getUnitFactory(): UnitFactoryInterface
    {
        return $this->unitFactory;
    }

    /**
     * Returns the unit parsed for this range request.
     *
     * @throws NoRangeException if a range request header could not be found.
     */
    public function getUnit(): UnitInterface
    {
        $rangeHeader = $this->getRequest()->getHeader('Range');

        if ($rangeHeader === [] || trim($rangeHeader[0]) === '') {
            throw new NoRangeException('The Range header is not present on this request or has no value');
        }

        // Use only the first Range header found, for now.
        return $this->getUnitFactory()->getUnit(trim($rangeHeader[0]), $this->getTotalSize());
    }
}
