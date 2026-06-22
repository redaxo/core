<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;

use function date;
use function strtotime;

/** Date picker (HTML5), stored as a Unix timestamp (local midnight). */
class DateField extends InputField
{
    protected function inputType(): string
    {
        return 'date';
    }

    protected function displayValue(): string
    {
        return null === $this->binding->value || '' === $this->binding->value
            ? ''
            : date('Y-m-d', (int) $this->binding->value);
    }

    public function parseRequest(string $name): mixed
    {
        $value = Request::post($name, 'string', '');

        return '' === $value ? null : (strtotime($value) ?: null);
    }

    public function column(string $name): Column
    {
        return new Column($name, 'int(11)', nullable: true);
    }
}
