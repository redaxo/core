<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;
use function sprintf;

/** Multi-line text input, stored as a text column. */
class TextareaField extends MetaField
{
    public function __construct(
        string $name,
        string $label,
        public readonly int $rows = 5,
        ?string $note = null,
        bool $required = false,
    ) {
        parent::__construct($name, $label, $note, $required);
    }

    public function column(MetaEntity $entity): ?Column
    {
        // `text` columns can not carry a default value in MySQL.
        return new Column($this->columnName($entity), 'text', nullable: true);
    }

    public function renderInput(MetaContext $context): string
    {
        $name = $this->columnName($context->entity);

        $attributes = new HtmlAttributes([
            'class' => ['form-control'],
            'name' => $name,
            'id' => $name,
            'rows' => $this->rows,
            'required' => $this->required,
        ]);

        return sprintf('<textarea %s>%s</textarea>', $attributes, escape((string) $context->value($this)));
    }
}
