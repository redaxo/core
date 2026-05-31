<?php

namespace Redaxo\Core\Addon;

/** Lifecycle state of an addon. */
enum AddonState: string
{
    /** Not installed. */
    case Uninstalled = 'uninstalled';

    /** Installed but not activated. */
    case Installed = 'installed';

    /** Installed and activated. */
    case Activated = 'activated';
}
