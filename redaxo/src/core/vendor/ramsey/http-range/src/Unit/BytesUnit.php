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
 * An HTTP Range bytes unit as defined in RFC 9110.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1.2 RFC 9110, section 14.1.2
 */
class BytesUnit extends AbstractUnit implements UnitInterface
{
    public function getRangeUnit(): string
    {
        return 'bytes';
    }

    public function newCollection(): UnitRangesCollection
    {
        return new BytesRangesCollection();
    }

    public function newRange(string $range, float | int | string $totalSize): UnitRangeInterface
    {
        return new BytesRange($range, $totalSize);
    }
}
