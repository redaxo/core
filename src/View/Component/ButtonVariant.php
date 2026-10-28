<?php

namespace Redaxo\Core\View\Component;

/** Look of a {@see Button}, by the weight and meaning of its action. */
enum ButtonVariant: string
{
    /** The neutral button, e.g. abort */
    case Secondary = 'secondary';
    /** The main action, e.g. send or select */
    case Primary = 'primary';
    /** Saving */
    case Success = 'success';
    case Warning = 'warning';
    /** Deleting and other destructive actions */
    case Danger = 'danger';
    /** Aligned with the input fields, e.g. the buttons of a picker */
    case Field = 'field';
    /** Looks like a link */
    case Link = 'link';
}
