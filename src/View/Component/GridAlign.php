<?php

namespace Redaxo\Core\View\Component;

/**
 * Alignment of a {@see GridColumn} within its cell, horizontally (`alignX`, in reading direction) or vertically
 * (`alignY`); anything but `Stretch` makes the column as wide or high as its content.
 */
enum GridAlign: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
    case Stretch = 'stretch';
}
