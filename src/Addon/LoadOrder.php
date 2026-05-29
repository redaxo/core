<?php

namespace Redaxo\Core\Addon;

/** Loading position of an addon relative to others during boot. */
enum LoadOrder
{
    /** Loaded before normal addons, ignoring dependencies. */
    case Early;

    /** Loaded in dependency order (default). */
    case Normal;

    /** Loaded after normal addons, ignoring dependencies. */
    case Late;
}
