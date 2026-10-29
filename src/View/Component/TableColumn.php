<?php

namespace Redaxo\Core\View\Component;

use Closure;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

use function get_debug_type;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

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
        public TableColumnType $type = TableColumnType::Text,
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
        /** Whether the cells are the headers of their rows, e.g. the labels of a table of properties */
        public bool $rowHeader = false,
        /** Label of the cells where the rows become blocks on small screens, defaults to a plain string label */
        public ?string $title = null,
    ) {}

    /**
     * Column showing the entry `$key` of rows given as arrays of prepared cells.
     *
     * @throws InvalidArgumentException for a row whose entry is no cell content
     */
    public static function forKey(
        string|Renderable|null $label,
        string|int $key,
        TableColumnType $type = TableColumnType::Text,
        HtmlAttributes $attributes = new HtmlAttributes(),
        bool $rowHeader = false,
        ?string $title = null,
    ): self {
        $cell = static function (mixed $row) use ($key): string|int|float|Renderable|TableCell|null {
            /** @var mixed $value */
            $value = is_array($row) ? $row[$key] ?? null : null;
            if (null === $value || is_string($value) || is_int($value) || is_float($value) || $value instanceof Renderable || $value instanceof TableCell) {
                return $value;
            }
            throw new InvalidArgumentException('The entry "' . $key . '" of the row is no cell content, ' . get_debug_type($value) . ' given.');
        };

        return new self($label, $cell, $type, attributes: $attributes, rowHeader: $rowHeader, title: $title);
    }
}
