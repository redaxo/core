<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function date;
use function Redaxo\Core\View\escape;
use function sprintf;
use function strtotime;

/** Date picker (HTML5), stored as a Unix timestamp (local midnight). */
class DateField extends MetaField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'int(11)', nullable: true);
    }

    public function parseRequest(MetaContext $ctx): int|string|null
    {
        $value = Request::post($this->columnName($ctx->entity), 'string', '');

        return '' === $value ? null : (strtotime($value) ?: null);
    }

    public function renderInput(MetaContext $ctx): string
    {
        $stored = $ctx->value($this);
        $value = null === $stored || '' === $stored ? '' : date('Y-m-d', (int) $stored);

        return sprintf(
            '<input class="form-control" type="date" name="%s" id="%1$s" value="%s"%s>',
            escape($this->columnName($ctx->entity)),
            escape($value),
            $this->required ? ' required' : '',
        );
    }
}
