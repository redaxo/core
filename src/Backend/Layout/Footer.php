<?php

namespace Redaxo\Core\Backend\Layout;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Footer of the backend.
 *
 * @internal
 */
final class Footer implements Renderable
{
    use RendersView;

    public function __construct(
        /** Formatted duration of the request so far */
        public string $time,
    ) {}
}
