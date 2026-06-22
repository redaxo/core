<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\View\HtmlAttributes;

/** Multi-line text input, stored as a text column. */
class TextareaField extends Field implements ProvidesColumn
{
    public function __construct(
        string $name,
        string $label = '',
        public readonly int $rows = 5,
        ?string $note = null,
        bool $required = false,
        HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct($name, $label, $note, $required, null, $attributes);
    }

    public function textareaAttributes(): HtmlAttributes
    {
        return $this->attributes()->with([
            'class' => ['form-control'],
            'name' => $this->binding->name,
            'id' => $this->binding->name,
            'rows' => $this->rows,
            'required' => $this->required,
        ]);
    }

    public function column(string $name): Column
    {
        // `text` columns can not carry a default value in MySQL.
        return new Column($name, 'text', nullable: true);
    }
}
