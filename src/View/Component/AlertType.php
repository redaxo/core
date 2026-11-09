<?php

namespace Redaxo\Core\View\Component;

/** Kind of an {@see Alert}. */
enum AlertType: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'danger';
}
