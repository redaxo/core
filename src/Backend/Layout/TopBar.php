<?php

namespace Redaxo\Core\Backend\Layout;

use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Top bar of the backend: logo, instance name, meta navigation and the toggle of the main navigation.
 *
 * @internal
 */
final class TopBar implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<Html> List items (`<li>`) of the meta navigation */
        public array $metaItems = [],
        /** Popup pages get a slim bar without navigation */
        public bool $popup = false,
        public bool $login = false,
    ) {}
}
