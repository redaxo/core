<?php

namespace Redaxo\Core\View\Component;

/** Start of a column in a {@see GridLayoutColumns}, besides a track number. */
enum GridColumnStart: string
{
    /** Right after the previous column */
    case Auto = 'auto';
}
