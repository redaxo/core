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

use Ramsey\Http\Range\Exception\InvalidRangeSetException;
use Ramsey\Http\Range\Exception\InvalidRangeUnitException;
use Ramsey\Http\Range\Unit\BytesUnit;
use Ramsey\Http\Range\Unit\GenericUnit;
use Ramsey\Http\Range\Unit\UnitInterface;

use function explode;
use function strtolower;

/**
 * A default factory for creating range units.
 */
class UnitFactory implements UnitFactoryInterface
{
    /**
     * Returns a parsed unit for the HTTP Range header.
     *
     * @param string $rangesSpecifier The original value of the HTTP Range header.
     * @param float | int | string $totalSize The total size of the entity described by this unit.
     *
     * @throws InvalidRangeUnitException if no range unit could be found.
     * @throws InvalidRangeSetException if no range set could be found.
     */
    public function getUnit(string $rangesSpecifier, float | int | string $totalSize): UnitInterface
    {
        $unitSet = explode('=', $rangesSpecifier);

        if ($unitSet[0] === '') {
            throw new InvalidRangeUnitException(
                'No range-unit provided in $rangesSpecifier',
            );
        }

        if (!isset($unitSet[1]) || $unitSet[1] === '') {
            throw new InvalidRangeSetException(
                'No range-set provided in $rangesSpecifier',
            );
        }

        return match (strtolower($unitSet[0])) {
            'bytes' => new BytesUnit($unitSet[1], $totalSize),
            default => new GenericUnit($unitSet[0], $unitSet[1], $totalSize),
        };
    }
}
