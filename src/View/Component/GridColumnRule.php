<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Exception\InvalidArgumentException;

use function is_int;

/**
 * What applies to a {@see GridColumn} from a breakpoint on.
 *
 * A value left out keeps the one of the smaller breakpoint. Which values a layout allows is checked by {@see Grid}.
 */
final readonly class GridColumnRule
{
    public function __construct(
        public Breakpoint $breakpoint,
        /**
         * Tracks of the 12, in {@see GridLayoutSplit} also `Auto` or `Fill`.
         *
         * @var int<1, 12>|GridColumnWidth|null
         */
        public int|GridColumnWidth|null $width = null,
        /**
         * First track (only {@see GridLayoutColumns}), `Auto` right after the previous column.
         *
         * @var int<1, 12>|GridColumnStart|null
         */
        public int|GridColumnStart|null $start = null,
        public ?GridOrder $order = null,
        public ?GridVisibility $visibility = null,
    ) {
        if (null === $width && null === $start && null === $order && null === $visibility) {
            throw new InvalidArgumentException('A grid column rule needs at least one value, the rule for "' . $breakpoint->value . '" has none.');
        }

        foreach (['width' => $width, 'start' => $start] as $name => $value) {
            if (is_int($value) && ($value < 1 || $value > GridLayoutColumns::COLUMNS)) {
                throw new InvalidArgumentException('The ' . $name . ' of a grid column must be between 1 and ' . GridLayoutColumns::COLUMNS . ', ' . $value . ' given.');
            }
        }
    }
}
