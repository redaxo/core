<?php

namespace Redaxo\Core\View\Component;

/** Visibility of a {@see GridColumn}. */
enum GridVisibility: string
{
    case Visible = 'visible';
    /** Not shown, also hidden from screen readers */
    case Hidden = 'hidden';
}
