<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;

use function explode;
use function gmdate;

/** Time picker (HTML5), stored as seconds since midnight. */
class TimeField extends InputField
{
    protected function inputType(): string
    {
        return 'time';
    }

    protected function displayValue(): string
    {
        return null === $this->binding->value || '' === $this->binding->value
            ? ''
            : gmdate('H:i', (int) $this->binding->value);
    }

    public function parseRequest(string $name): mixed
    {
        $value = Request::post($name, 'string', '');

        if ('' === $value) {
            return null;
        }

        $parts = explode(':', $value);

        return ((int) ($parts[0] ?? 0) * 3600) + ((int) ($parts[1] ?? 0) * 60);
    }

    public function column(string $name): Column
    {
        return new Column($name, 'int(11)', nullable: true);
    }
}
