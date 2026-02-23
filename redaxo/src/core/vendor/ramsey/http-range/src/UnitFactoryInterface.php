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

use Ramsey\Http\Range\Unit\UnitInterface;

/**
 * `UnitFactoryInterface` defines a factory interface for creating range units.
 */
interface UnitFactoryInterface
{
    /**
     * Returns a parsed unit for the HTTP Range header
     *
     * @param string $rangesSpecifier The original value of the HTTP Range header.
     * @param float | int | string $totalSize The total size of the entity described by this unit.
     */
    public function getUnit(string $rangesSpecifier, float | int | string $totalSize): UnitInterface;
}
