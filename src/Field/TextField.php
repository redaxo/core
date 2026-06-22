<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\View\HtmlAttributes;

/** Single-line text input, stored as a varchar column. */
class TextField extends InputField
{
    public function __construct(
        string $name,
        string $label = '',
        public readonly int $maxLength = 255,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
        HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct($name, $label, $note, $required, $default, $attributes);
    }

    protected function inputType(): string
    {
        return 'text';
    }

    public function inputAttributes(): HtmlAttributes
    {
        return parent::inputAttributes()->with(['maxlength' => $this->maxLength]);
    }

    public function column(string $name): Column
    {
        return new Column($name, 'varchar(' . $this->maxLength . ')', nullable: true, default: $this->default);
    }
}
