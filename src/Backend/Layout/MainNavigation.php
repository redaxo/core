<?php

namespace Redaxo\Core\Backend\Layout;

use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Main navigation of the backend (the sidebar), with the backdrop that closes it on small screens.
 *
 * @internal
 */
final class MainNavigation implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<array{navigation: list<array<string, mixed>>, headline: array{title: string}}> Blocks as returned by {@see Navigation::getNavigation()} */
        public array $blocks,
    ) {}
}
