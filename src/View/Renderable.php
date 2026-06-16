<?php

namespace Redaxo\Core\View;

/**
 * Anything that renders itself to safe HTML.
 *
 * The common content type of the view system: a renderable can be rendered to {@see Html} and is
 * therefore accepted anywhere {@see Html} is — e.g. as another component's content, without the
 * caller having to render it first.
 *
 * Most implementations get {@see Renderable::render()} for free from {@see RendersView}.
 */
interface Renderable
{
    public function render(): Html;
}
