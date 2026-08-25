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

/** Date+time picker (HTML5), stored as a SQL `datetime` (`Y-m-d H:i:s`). */
class DateTimeField extends AbstractInputField
{
    public function column(MetaEntity $entity): ?Column
    {
        return Column::datetime($this->columnName($entity), nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        // HTML datetime-local uses a "T" separator.
        $value = Request::post($this->columnName($context->entity), 'string', '');
        $time = '' === $value ? false : strtotime(str_replace('T', ' ', $value));

        return false === $time ? null : date('Y-m-d H:i:s', $time);
    }

    public function format(mixed $stored): ?int
    {
        // Expose a Unix timestamp, consistent with Sql::getDateTimeValue().
        return null === $stored || '' === $stored ? null : (strtotime((string) $stored) ?: null);
    }

    public function renderInput(MetaContext $context): string
    {
        $stored = $context->value($this);
        $time = null === $stored || '' === $stored ? false : strtotime((string) $stored);
        // The datetime-local input wants `Y-m-dTH:i`, without seconds.
        $value = false === $time ? '' : date('Y-m-d\TH:i', $time);
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
