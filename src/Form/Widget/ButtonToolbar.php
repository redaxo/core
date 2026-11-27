<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** The submit buttons of a form together with its cancel link. */
final class ButtonToolbar implements Renderable
{
    use RendersView;

    /** @internal */
    public function __construct(
        /** @var list<Button> */
        public array $buttons,
        public ?CancelLink $cancelLink = null,
        /** Whether the first button starts at the fields of a form with labels beside them */
        public bool $aligned = true,
    ) {}
}
