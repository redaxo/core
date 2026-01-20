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

use Ramsey\Http\Range\Exception\NotSatisfiableException;
use Ramsey\Http\Range\Exception\ParseException;

use function array_filter;
use function array_map;
use function ctype_digit;
use function explode;
use function trim;

/**
 * `AbstractUnitRange` provides a basic implementation for unit ranges.
 *
 * The basic implementation treats ranges and total size as if they are integers.
 * Extend and override this class or implement UnitRangeInterface to treat the
 * ranges and total size as other kinds of values, depending on your use case.
 */
abstract class AbstractUnitRange implements UnitRangeInterface
{
    private readonly int $totalSize;

    private readonly int $start;

    private readonly int $end;

    /**
     * Constructs a new unit range.
     *
     * @param string $range A single range (i.e. `500-999`, `500-`, `-500`).
     * @param float | int | string $totalSize The total size of the entity the range describes.
     *
     * @throws ParseException if unable to parse the range.
     * @throws NotSatisfiableException if the range cannot be satisfied.
     */
    public function __construct(private readonly string $range, float | int | string $totalSize)
    {
        $this->totalSize = (int) $totalSize;
        [$this->start, $this->end] = $this->parseRange($range, $this->totalSize);
    }

    public function getRange(): string
    {
        return $this->range;
    }

    public function getStart(): float | int | string
    {
        return $this->start;
    }

    public function getEnd(): float | int | string
    {
        return $this->end;
    }

    public function getLength(): float | int | string
    {
        return (int) $this->getEnd() - (int) $this->getStart() + 1;
    }

    public function getTotalSize(): float | int | string
    {
        return $this->totalSize;
    }

    /**
     * Parses the given range, returning a 2-tuple where the first value is the
     * start and the second is the end.
     *
     * @param string $range The range string to parse.
     * @param int $totalSize The total size of the entity.
     *
     * @return array{int, int}
     *
     * @throws ParseException if unable to parse the range.
     * @throws NotSatisfiableException if the range cannot be satisfied.
     */
    private function parseRange(string $range, int $totalSize): array
    {
        $points = explode('-', $range, 2);

        if (!isset($points[1])) {
            // Assume the request is for a single item.
            $points[1] = $points[0];
        }

        $points = array_map(trim(...), $points);
        $isValidRangeValue = fn (string $value): bool => ctype_digit($value) || $value === '';

        if (array_filter($points, ctype_digit(...)) === [] || array_filter($points, $isValidRangeValue) !== $points) {
            throw new ParseException("Unable to parse range: {$range}");
        }

        $start = $points[0];
        $end = $points[1] !== '' ? (int) $points[1] : $totalSize - 1;

        if ($end >= $totalSize) {
            $end = $totalSize - 1;
        }

        if ($start === '') {
            // Use the "suffix-range".
            $start = $totalSize - $end;
            $end = $totalSize - 1;
        }

        $start = (int) $start;

        if ($start === $totalSize) {
            throw new NotSatisfiableException("Unable to satisfy range: {$range}; length is zero", $range, $totalSize);
        }

        if ($start > $totalSize) {
            throw new NotSatisfiableException(
                "Unable to satisfy range: {$range}; start ({$start}) is greater than size ({$totalSize})",
                $range,
                $totalSize,
            );
        }

        if ($end < $start) {
            throw new ParseException("The end value cannot be less than the start value: {$range}");
        }

        return [$start, $end];
    }
}
