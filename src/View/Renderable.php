<?php

namespace Redaxo\Core\View;

/**
 * Anything that renders itself to safe HTML.
 *
 * The common type for view components (and, in time, templates and modules): it can be rendered to
 * {@see Html} and therefore embedded anywhere {@see Html} is accepted.
 *
 * Most implementations get {@see Renderable::render()} for free from {@see RendersView}.
 */
interface Renderable
{
    public function render(): Html;
}
