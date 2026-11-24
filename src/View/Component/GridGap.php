<?php

namespace Redaxo\Core\View\Component;

/** Space between the columns and rows of a {@see Grid}. */
enum GridGap: string
{
    case None = 'none';
    case Small = 'sm';
    /** The gutter of the backend */
    case Default = 'default';
    case Large = 'lg';
}
