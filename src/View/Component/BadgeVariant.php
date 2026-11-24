<?php

namespace Redaxo\Core\View\Component;

/** Color scheme of a {@see Badge}. */
enum BadgeVariant: string
{
    case Neutral = 'neutral';
    case Primary = 'primary';
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
}
