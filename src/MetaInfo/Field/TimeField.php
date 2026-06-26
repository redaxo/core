<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function date;
use function explode;
use function sprintf;
use function strtotime;
use function substr;

/** Time picker (HTML5), stored as a SQL `time` (`H:i:s`). */
class TimeField extends AbstractInputField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'time', nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        $value = Request::post($this->columnName($context->entity), 'string', '');
        $time = '' === $value ? false : strtotime($value);

        return false === $time ? null : date('H:i:s', $time);
    }

    public function format(mixed $stored): ?int
    {
        if (null === $stored || '' === $stored) {
            return null;
        }

        // A `time` column has no date part, so the natural int is seconds since midnight.
        $parts = explode(':', (string) $stored);

        return ((int) ($parts[0] ?? 0) * 3600) + ((int) ($parts[1] ?? 0) * 60) + (int) ($parts[2] ?? 0);
    }

    public function renderInput(MetaContext $context): string
    {
        $stored = $context->value($this);
        // The stored value is `H:i:s`; the time input shows `H:i`.
        $value = null === $stored || '' === $stored ? '' : substr((string) $stored, 0, 5);
        $name = $this->columnName($context->entity);

        $attributes = $this->attributes->with([
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
