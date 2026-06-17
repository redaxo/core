<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function explode;
use function gmdate;
use function Redaxo\Core\View\escape;
use function sprintf;

/** Time picker (HTML5), stored as seconds since midnight. */
class TimeField extends MetaField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'int(11)', nullable: true);
    }

    public function parseRequest(MetaContext $ctx): int|string|null
    {
        $value = Request::post($this->columnName($ctx->entity), 'string', '');

        if ('' === $value) {
            return null;
        }

        $parts = explode(':', $value);

        return ((int) ($parts[0] ?? 0) * 3600) + ((int) ($parts[1] ?? 0) * 60);
    }

    public function renderInput(MetaContext $ctx): string
    {
        $stored = $ctx->value($this);
        $value = null === $stored || '' === $stored ? '' : gmdate('H:i', (int) $stored);

        return sprintf(
            '<input class="form-control" type="time" name="%s" id="%1$s" value="%s"%s>',
            escape($this->columnName($ctx->entity)),
            escape($value),
            $this->required ? ' required' : '',
        );
    }
}
