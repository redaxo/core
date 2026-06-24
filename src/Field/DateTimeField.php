<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;

use function date;
use function str_replace;
use function strtotime;

/** Date+time picker (HTML5), stored as a Unix timestamp. */
class DateTimeField extends InputField
{
    public function displayValue(): string
    {
        return null === $this->binding->value || '' === $this->binding->value
            ? ''
            : date('Y-m-d\TH:i', (int) $this->binding->value);
    }

    public function parseRequest(string $name): mixed
    {
        // HTML datetime-local uses a "T" separator.
        $value = Request::post($name, 'string', '');

        return '' === $value ? null : (strtotime(str_replace('T', ' ', $value)) ?: null);
    }

    public function column(string $name): Column
    {
        return new Column($name, 'int(11)', nullable: true);
    }
}
