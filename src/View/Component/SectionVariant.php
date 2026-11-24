<?php

namespace Redaxo\Core\View\Component;

/** Color scheme of a {@see Section}. */
enum SectionVariant: string
{
    case Default = 'default';
    /** Section holding a form to edit something */
    case Edit = 'edit';
    /** Section holding a form to add something */
    case Add = 'add';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
}
