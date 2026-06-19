<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function date;
use function Redaxo\Core\View\escape;
use function sprintf;
use function str_replace;
use function strtotime;

/** Date+time picker (HTML5), stored as a Unix timestamp. */
class DateTimeField extends MetaField
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

        return sprintf(
            '<input class="form-control" type="datetime-local" name="%s" id="%1$s" value="%s"%s>',
            escape($this->columnName($context->entity)),
            escape($value),
            $this->required ? ' required' : '',
        );
    }
}
