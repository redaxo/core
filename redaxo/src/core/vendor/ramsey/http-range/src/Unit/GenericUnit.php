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
 * A generic HTTP Range unit.
 */
class GenericUnit extends AbstractUnit implements UnitInterface
{
    /**
     * Constructs a new generic unit.
     *
     * @param string $rangeUnit The range unit this generic unit represents.
     * @param string $rangeSet A set of ranges for this unit (i.e. `500-999,500-,-500`).
     * @param float | int | string $totalSize The total size of the entity the unit describes.
     */
    public function __construct(private readonly string $rangeUnit, string $rangeSet, float | int | string $totalSize)
    {
        parent::__construct($rangeSet, $totalSize);
    }

    public function getRangeUnit(): string
    {
        return $this->rangeUnit;
    }

    public function newCollection(): UnitRangesCollection
    {
        return new UnitRangesCollection();
    }

    public function newRange(string $range, float | int | string $totalSize): UnitRangeInterface
    {
        return new GenericRange($range, $totalSize);
    }
}
