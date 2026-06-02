<?php

namespace Redaxo\Core\ExtensionPoint;

/** Run level controlling the order in which extensions of an extension point are executed. */
enum ExtensionLevel
{
    /** Executed before normal extensions. */
    case Early;

    /** Executed in registration order (default). */
    case Normal;

    /** Executed after normal extensions. */
    case Late;
}
