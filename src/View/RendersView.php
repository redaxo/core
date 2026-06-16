<?php

namespace Redaxo\Core\View;

/**
 * Default {@see Renderable::render()} implementation backed by a co-located view file.
 *
 * Hands the whole object to the view (`ClassName.view.php`, resolved by {@see HasView}) and wraps
 * the captured output as trusted {@see Html}. The typical choice for a self-contained component.
 */
trait RendersView
{
    use HasView;

    final public function render(): Html
    {
        return Html::raw($this->renderView($this));
    }
}
