<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\View\HtmlAttributes;

use function explode;
use function gmdate;
use function sprintf;

/** Time picker (HTML5), stored as seconds since midnight. */
class TimeField extends MetaField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'int(11)', nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        $value = Request::post($this->columnName($context->entity), 'string', '');

        if ('' === $value) {
            return null;
        }

        $parts = explode(':', $value);

        return ((int) ($parts[0] ?? 0) * 3600) + ((int) ($parts[1] ?? 0) * 60);
    }

    public function renderInput(MetaContext $context): string
    {
        $stored = $context->value($this);
        $value = null === $stored || '' === $stored ? '' : gmdate('H:i', (int) $stored);
        $name = $this->columnName($context->entity);

        $attributes = new HtmlAttributes([
            'class' => ['form-control'],
            'type' => 'time',
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'required' => $this->required,
        ]);

        return sprintf('<input %s>', $attributes);
    }
}
