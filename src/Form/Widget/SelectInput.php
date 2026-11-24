<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\RendersView;

/**
 * Select box for one or (with `multiple`) several of a set of choices.
 *
 * Choices are given as `value => label`; a nested array becomes an optgroup, its key being the group label.
 */
final class SelectInput implements Widget
{
    use GroupsChoices;
    use RendersView;

    public function __construct(
        /** For `multiple` the `[]` suffix is appended automatically. */
        public string $name,
        /** @var array<int|string, string|Translatable|array<int|string, string|Translatable>> */
        public array $choices,
        /** @var string|list<string>|null */
        public string|array|null $selected = null,
        public ?string $id = null,
        public bool $multiple = false,
        /** Label of an empty option; `null` renders none. Ignored for `multiple`. */
        public string|Translatable|null $placeholder = null,
        public bool $required = false,
        public bool $disabled = false,
        /** Adds a search field to filter the choices. */
        public bool $search = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}

    public function labelTarget(): ?string
    {
        return $this->id;
    }
}
