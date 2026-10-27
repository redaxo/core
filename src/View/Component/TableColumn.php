<?php

namespace Redaxo\Core\View\Component;

use Closure;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

/**
 * Column of a {@see Table}: the header and how the cell of each row is rendered.
 *
 * Plain strings returned by `cell` are escaped, markup is returned as {@see Html} or another {@see Renderable}.
 */
final class TableColumn
{
    public function __construct(
        /** Header; `null` joins the header of the previous column, e.g. for several actions under one header */
        public string|Renderable|null $label,
        /** @var Closure(mixed): (string|int|float|Renderable|TableCell|null) cell of a row */
        public Closure $cell,
        public ColumnType $type = ColumnType::Text,
        /** @var (Closure(mixed): ?string)|null url the cell links to, `null` for a row without link */
        public ?Closure $link = null,
        /** @var array<literal-string, string|bool> e.g. `data-confirm` */
        public array $linkAttributes = [],
        /** Link of the header sorting the table by this column */
        public ?string $sortUrl = null,
        /** @var 'asc'|'desc'|null direction while the table is sorted by this column */
        public ?string $sorted = null,
        /** Attributes of the header and the cells, e.g. a class */
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
