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

use function explode;

/**
 * `AbstractUnit` provides a basic implementation for HTTP range units.
 */
abstract class AbstractUnit implements UnitInterface
{
    /**
     * Returns a new collection for this range unit.
     */
    abstract public function newCollection(): UnitRangesCollection;

    /**
     * Returns a new unit range for this range unit.
     *
     * @param string $range A single range (i.e. `500-999`, `500-`, `-500`).
     * @param float | int | string $totalSize The total size of the entity the range describes.
     */
    abstract public function newRange(string $range, float | int | string $totalSize): UnitRangeInterface;

    /**
     * Constructs a new unit.
     *
     * @param string $rangeSet A set of ranges for this unit (i.e. `500-999,500-,-500`).
     * @param float | int | string $totalSize The total size of the entity the unit describes.
     */
    public function __construct(
        private readonly string $rangeSet,
        private readonly float | int | string $totalSize,
    ) {
    }

    public function getRangeSet(): string
    {
        return $this->rangeSet;
    }

    public function getRangesSpecifier(): string
    {
        return $this->getRangeUnit() . '=' . $this->getRangeSet();
    }

    public function getRanges(): UnitRangesCollection
    {
        $ranges = explode(',', $this->getRangeSet());
        $collection = $this->newCollection();
        $totalSize = $this->getTotalSize();

        foreach ($ranges as $range) {
            $collection[] = $this->newRange($range, $totalSize);
        }

        return $collection;
    }

    public function getTotalSize(): float | int | string
    {
        return $this->totalSize;
    }
}
