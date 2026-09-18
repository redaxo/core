<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\View\HtmlAttributes;

use function sprintf;

/** Single-line text input, stored as a varchar column. */
class TextField extends AbstractInputField
{
    public function __construct(
        string $name,
        string $label,
        /** @var int<1, 65535> */
        public readonly int $maxLength = 255,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
        bool $translatable = false,
        HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct($name, $label, $note, $required, $default, $translatable, $attributes);
    }

    public function column(): ?Column
    {
        return Column::varchar($this->columnName(), $this->maxLength, nullable: true, default: $this->default);
    }

    public function renderInput(MetaContext $context): string
    {
        $name = $this->columnName();

        $attributes = $this->attributes->with([
            'class' => ['form-control'],
            'type' => 'text',
            'name' => $name,
            'id' => $name,
            'value' => (string) $context->value($this),
            'maxlength' => $this->maxLength,
            'required' => $this->required,
        ]);

        return sprintf('<input %s>', $attributes);
    }
}
