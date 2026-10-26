<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\View\RendersView;

/**
 * Picker for one article via the linkmap overlay.
 *
 * The `id` belongs to the visible (read-only) name input, so a label can point to it; the article id is submitted by
 * a hidden input.
 */
final class ArticleInput implements Widget
{
    use RendersView;

    public function __construct(
        public string $name,
        public string $id,
        public ?int $articleId = null,
        /** Category the linkmap opens in when no article is selected; defaults to the current category. */
        public ?int $categoryId = null,
        public bool $disabled = false,
    ) {}

    public function labelTarget(): string
    {
        return $this->id;
    }
}
