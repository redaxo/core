<?php

namespace Redaxo\Core\View;

/**
 * Renders a class's view file.
 *
 * The view is resolved by {@see ViewResolver} (co-located `ClassName.view.php` by default,
 * overridable per FQCN). Unlike {@see RendersView} this makes no assumption about what is handed to
 * the view, so it also fits classes whose render entry point is not a parameterless `render(): Html`
 * — e.g. a template passing its `ArticleContent`.
 *
 * Returns the raw rendered string; callers that want the safe-HTML currency wrap it themselves (as
 * {@see RendersView::render()} does), while classes bound to a legacy `string` contract use it directly.
 */
trait HasView
{
    /** Renders the resolved view file, spreading the given arguments into its closure. */
    private function renderView(mixed ...$args): string
    {
        return Renderer::render(ViewResolver::resolve(static::class), $args);
    }
}
