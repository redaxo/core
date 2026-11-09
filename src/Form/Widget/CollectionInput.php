<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\Form\Field\Collection;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\RendersView;

/**
 * The items of a {@see Collection}, which can be added, removed and reordered.
 *
 * New items are created in the browser from the template, replacing the placeholder by an index not used yet.
 */
final class CollectionInput implements Widget
{
    use RendersView;

    public function __construct(
        public string $id,
        /** @var list<Html> the rendered fields of each item */
        public array $items,
        /** The rendered fields of a new item, with the placeholder in place of its index. */
        public Html $template,
        public string $placeholder,
        /** @var positive-int|null */
        public ?int $max = null,
        public bool $disabled = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}

    public function labelTarget(): ?string
    {
        return null;
    }
}
