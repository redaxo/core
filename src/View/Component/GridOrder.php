<?php

namespace Redaxo\Core\View\Component;

/**
 * Visual position of a {@see GridColumn}.
 *
 * Keyboard and screen readers keep the order of the source, so only change it where content and focus order stay
 * understandable (WCAG 1.3.2, 2.4.3).
 */
enum GridOrder: string
{
    /** Position in the source */
    case Source = 'source';
    /** Before all columns in source order */
    case First = 'first';
    /** After all columns in source order */
    case Last = 'last';
}
