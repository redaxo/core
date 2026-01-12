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
 * An HTTP Range bytes range unit as defined in RFC 9110.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9110.html#section-14.1.2 RFC 9110, section 14.1.2
 */
class BytesRange extends AbstractUnitRange implements UnitRangeInterface
{
}
