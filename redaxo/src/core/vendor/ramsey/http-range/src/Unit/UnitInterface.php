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

namespace Ramsey\Http\Range\Unit;

/**
 * `UnitInterface` defines an interface for HTTP Range units as defined in RFC 9110.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1 RFC 9110, section 14.1
 */
interface UnitInterface
{
    /**
     * Returns the raw range set defined for this unit.
     *
     *     range-set = 1#range-spec
     *     range-spec = int-range / suffix-range / other-range
     *     int-range = first-pos "-" [ last-pos ]
     *     first-pos = 1*DIGIT
     *     last-pos = 1*DIGIT
     *     suffix-range = "-" suffix-length
     *     suffix-length = 1*DIGIT
     *     other-range = 1*( %x21-2B / %x2D-7E )
     *
     * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1.1 RFC 9110, section 14.1.1
     */
    public function getRangeSet(): string;

    /**
     * Returns the range unit token defined for this unit.
     *
     *     range-unit = token
     *
     * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1.1 RFC 9110, section 14.1.1
     */
    public function getRangeUnit(): string;

    /**
     * Returns the raw ranges specifier defined for this unit.
     *
     *     ranges-specifier = range-unit "=" range-set
     *
     * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1.1 RFC 9110, section 14.1.1
     */
    public function getRangesSpecifier(): string;

    /**
     * Returns an iterable collection of unit ranges.
     */
    public function getRanges(): UnitRangesCollection;

    /**
     * Returns the total size of the entity this unit describes.
     *
     * For example, if this unit describes the bytes in a file, then this
     * returns the total bytes of the file.
     */
    public function getTotalSize(): float | int | string;
}
