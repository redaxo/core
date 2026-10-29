<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\RendersView;

/**
 * Picker for one medium via the media pool overlay.
 *
 * The file name is submitted by the (read-only) text input.
 */
final class MediaInput implements Widget
{
    use RendersView;

    public function __construct(
        public string $name,
        public string $id,
        public ?string $fileName = null,
        /** Media category the media pool opens in. */
        public ?int $categoryId = null,
        /** @var list<string> Allowed file extensions, e.g. `['jpg', 'png']`; empty allows all. */
        public array $types = [],
        /** Whether to show a preview of the selected medium. */
        public bool $preview = false,
        public bool $disabled = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}

    public function labelTarget(): string
    {
        return $this->id;
    }
}
