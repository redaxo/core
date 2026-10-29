<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

/** Cell of a {@see Table} with its own span or attributes, e.g. a form field spanning the action columns. */
final class TableCell
{
    public function __construct(
        public string|int|float|Renderable|null $content,
        /** Number of columns the cell spans, the following cells of the row are left out */
        public int $colspan = 1,
        public HtmlAttributes $attributes = new HtmlAttributes(),
        /** Whether the cell is the header of its row (`<th>`) */
        public bool $header = false,
    ) {}
}
