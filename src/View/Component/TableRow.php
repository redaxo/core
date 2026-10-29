<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;

/** Row of a {@see Table} with its own cells instead of those of the columns, e.g. a row of additional fields. */
final class TableRow
{
    public function __construct(
        /** @var list<TableCell> */
        public array $cells,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
