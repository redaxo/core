<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\View\Html;

/** A single boolean checkbox, stored as 0/1. */
class CheckboxField extends Field implements ProvidesColumn
{
    public function parseRequest(string $name): mixed
    {
        return Request::post($name, 'bool', false) ? 1 : 0;
    }

    public function format(mixed $stored): bool
    {
        return (bool) $stored;
    }

    public function column(string $name): Column
    {
        return new Column($name, 'tinyint(1)', nullable: false, default: '0');
    }

    /** The checkbox uses its own group chrome: the control sits inside the `<label>`. */
    public function render(): Html
    {
        return new CheckboxGroup($this)->render();
    }
}
