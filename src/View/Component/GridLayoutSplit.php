<?php

namespace Redaxo\Core\View\Component;

/**
 * The columns of a {@see Grid} share exactly one row, without wrapping.
 *
 * Without a width the columns are equally wide ({@see GridColumnWidth::Fill}), also five of them; a number is that
 * share of 12 of the row, {@see GridColumnWidth::Auto} as wide as the content.
 */
final class GridLayoutSplit implements GridLayout
{
    public function __construct(
        /** Below it the columns stack, from it on they share one row */
        public Breakpoint $from = Breakpoint::Xs,
    ) {}
}
