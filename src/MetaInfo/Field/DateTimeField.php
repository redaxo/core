<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function date;
use function sprintf;
use function str_replace;
use function strtotime;

/** Date+time picker (HTML5), stored as a Unix timestamp. */
class DateTimeField extends AbstractInputField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'int(11)', nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        // HTML datetime-local uses a "T" separator.
        $value = Request::post($this->columnName($context->entity), 'string', '');

        return '' === $value ? null : (strtotime(str_replace('T', ' ', $value)) ?: null);
    }

    public function renderInput(MetaContext $context): string
    {
        $stored = $context->value($this);
        $value = null === $stored || '' === $stored ? '' : date('Y-m-d\TH:i', (int) $stored);
        $name = $this->columnName($context->entity);

        $attributes = $this->attributes->with([
            'class' => ['form-control'],
            'type' => 'datetime-local',
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'required' => $this->required,
        ]);

        return sprintf('<input %s>', $attributes);
    }
}
