<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\View\RendersView;

/**
 * Picker for an ordered list of articles via the linkmap overlay.
 *
 * The article ids are submitted comma-separated by a hidden input.
 */
final class ArticleListInput implements Widget
{
    use RendersView;

    public function __construct(
        public string $name,
        public string $id,
        /** @var list<int> */
        public array $articleIds = [],
        /** Category the linkmap opens in; defaults to the current category. */
        public ?int $categoryId = null,
        public bool $disabled = false,
    ) {}

    public function labelTarget(): string
    {
        return $this->id;
    }
}
