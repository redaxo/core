<?php

namespace Redaxo\Core\View\Component;

/** Width of a column in a {@see GridLayoutSplit}, besides a number of tracks. */
enum GridColumnWidth: string
{
    /** As wide as the content, shrinks so long content wraps */
    case Auto = 'auto';
    /** Shares the remaining width with the other `Fill` columns, may shrink to 0 */
    case Fill = 'fill';
}
