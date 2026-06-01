<?php

namespace Redaxo\Core;

/** The runtime environment in which REDAXO is running. */
enum Environment: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Console = 'console';
}
