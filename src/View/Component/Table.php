<?php

namespace Redaxo\Core\View\Component;

use Closure;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Table of rows with the cells defined by its {@see TableColumn}s.
 *
 * Below 768px each row becomes a block, the cells are labeled with the column headers.
 */
final class Table implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<TableColumn> */
        public array $columns,
        /** @var iterable<mixed> rows handed to the cell closures; a {@see TableRow} brings its own cells */
        public iterable $rows,
        public string|Renderable $caption = '',
        /** Shown in a single row when there are no rows */
        public string|Renderable $empty = '',
        /** @var (Closure(mixed): HtmlAttributes)|null attributes of a row, e.g. a state class */
        public ?Closure $rowAttributes = null,
        /** Rows of the `<tfoot>`, e.g. actions for the selected rows */
        public string|Renderable $footer = '',
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
