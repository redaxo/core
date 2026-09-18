<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;

use function date;
use function sprintf;
use function strtotime;

/** Date picker (HTML5), stored as a SQL `date` (`Y-m-d`). */
class DateField extends AbstractInputField
{
    public function column(): ?Column
    {
        return Column::date($this->columnName(), nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        $value = Request::post($this->columnName(), 'string', '');
        $time = '' === $value ? false : strtotime($value);

        return false === $time ? null : date('Y-m-d', $time);
    }

    public function format(mixed $stored): ?int
    {
        // Expose a Unix timestamp (local midnight), consistent with Sql::getDateTimeValue().
        return null === $stored || '' === $stored ? null : (strtotime((string) $stored) ?: null);
    }

    public function renderInput(MetaContext $context): string
    {
        $stored = $context->value($this);
        // The stored value is already `Y-m-d`, exactly what the date input expects.
        $value = null === $stored || '' === $stored ? '' : (string) $stored;
        $name = $this->columnName();

        $attributes = $this->attributes->with([
            'class' => ['form-control'],
            'type' => 'date',
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'required' => $this->required,
        ]);

        return sprintf('<input %s>', $attributes);
    }
}
