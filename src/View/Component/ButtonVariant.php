<?php

namespace Redaxo\Core\View\Component;

/** Meaning of a {@see Button}, which determines its look. */
enum ButtonVariant: string
{
    case Default = 'default';
    case Primary = 'primary';
    case Save = 'save';
    /** Saves without leaving the form */
    case Apply = 'apply';
    case Delete = 'delete';
    case Abort = 'abort';
    case Edit = 'edit';
    case Send = 'send';
    case Select = 'select';
}
