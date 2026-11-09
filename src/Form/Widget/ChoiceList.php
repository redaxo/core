<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\RendersView;

use function preg_replace;

/**
 * Radio buttons, or (with `multiple`) checkboxes, for a set of choices.
 *
 * Choices are given as `value => label`; a nested array becomes a fieldset, its key being the legend.
 */
final class ChoiceList implements Widget
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
        /** Prefix of the option ids, also the id of the wrapping element. */
        public ?string $id = null,
        public bool $multiple = false,
        public bool $inline = false,
        public bool $disabled = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}

    public function optionId(int|string $value): ?string
    {
        return null === $this->id ? null : $this->id . '-' . (string) preg_replace('/[^\w-]/', '_', (string) $value);
    }

    public function labelTarget(): ?string
    {
        return null;
    }
}
