<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\View\HtmlAttributes;

use function sprintf;

/** Single-line text input, stored as a varchar column. */
class TextField extends MetaField
{
    public function __construct(
        string $name,
        string $label,
        public readonly int $maxLength = 255,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
    ) {
        parent::__construct($name, $label, $note, $required, $default);
    }

    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'varchar(' . $this->maxLength . ')', nullable: true, default: $this->default);
    }

    public function renderInput(MetaContext $context): string
    {
        $name = $this->columnName($context->entity);

        $attributes = new HtmlAttributes([
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
