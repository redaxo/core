<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

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

    public function renderInput(MetaContext $ctx): string
    {
        return sprintf(
            '<textarea class="form-control" name="%s" id="%1$s" rows="%d"%s>%s</textarea>',
            escape($this->columnName($ctx->entity)),
            $this->rows,
            $this->required ? ' required' : '',
            escape((string) $ctx->value($this)),
        );
    }
}
