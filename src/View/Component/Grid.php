<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

use function is_int;

/**
 * Columns side by side, responsive per breakpoint of the grid's own width ({@see Breakpoint}).
 *
 * The grid sets how the columns are arranged as a whole ({@see GridLayout}, gap); each {@see GridColumn} carries its
 * content, its alignment and its rules, and each {@see GridColumnRule} what applies from a breakpoint on.
 *
 * ```php
 * echo new Grid([
 *     new GridColumn($main, [new GridColumnRule(Breakpoint::Lg, width: 8)]),
 *     new GridColumn($aside, [new GridColumnRule(Breakpoint::Lg, width: 4)]),
 * ], gap: GridGap::Large)->render();
 *
 * echo new Grid([$a, $b, $c, $d, $e], layout: new GridLayoutSplit(from: Breakpoint::Md))->render();
 * echo new Grid($cards, layout: new GridLayoutTiles(GridTileSize::Small))->render();
 * ```
 *
 * Without columns the grid renders nothing. Invalid combinations (e.g. a width in tiles, start and width beyond
 * the 12 tracks, numbers of a split row above 12) throw an {@see InvalidArgumentException}.
 */
final readonly class Grid implements Renderable
{
    use RendersView;

    /** @var list<GridColumn> */
    public array $columns;

    /**
     * @param list<GridColumn|string|Renderable> $columns plain content becomes a column without rules
     * @param bool $subgrid as content of a {@see GridColumn} of another grid, the columns use the tracks of that grid
     * @param HtmlAttributes $attributes further attributes, e.g. an `id`
     */
    public function __construct(
        array $columns,
        public GridLayout $layout = new GridLayoutColumns(),
        public GridGap $gap = GridGap::Default,
        public bool $subgrid = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        $this->columns = array_map(static fn (GridColumn|string|Renderable $column): GridColumn => $column instanceof GridColumn ? $column : new GridColumn($column), $columns);

        foreach ($this->columns as $column) {
            match (true) {
                $layout instanceof GridLayoutColumns => self::validateColumns($column),
                $layout instanceof GridLayoutSplit => self::validateSplitColumn($column, $layout),
                default => self::validateTile($column),
            };
        }

        if ($layout instanceof GridLayoutSplit) {
            $this->validateSplitRow($layout);
        }
    }

    /** @internal for a {@see GridColumn} with this grid as content, its attributes take precedence */
    public function withAttributes(HtmlAttributes $attributes): self
    {
        return new self($this->columns, $this->layout, $this->gap, $this->subgrid, $this->attributes->merge($attributes));
    }

    private static function validateColumns(GridColumn $column): void
    {
        foreach ($column->rules as $rule) {
            if ($rule->width instanceof GridColumnWidth) {
                throw new InvalidArgumentException('The width "' . $rule->width->value . '" is only allowed in the split layout of a grid.');
            }
        }

        $widths = $column->resolve(static fn (GridColumnRule $rule): ?int => is_int($rule->width) ? $rule->width : null, GridLayoutColumns::COLUMNS);
        $starts = $column->resolve(static fn (GridColumnRule $rule): ?int => $rule->start instanceof GridColumnStart ? 0 : $rule->start, 0);
        foreach ($starts as $breakpoint => $start) {
            if ($start > 0 && $start + $widths[$breakpoint] - 1 > GridLayoutColumns::COLUMNS) {
                throw new InvalidArgumentException('A grid column starting at track ' . $start . ' with a width of ' . $widths[$breakpoint] . ' exceeds the ' . GridLayoutColumns::COLUMNS . ' tracks from the breakpoint "' . $breakpoint . '" on.');
            }
        }
    }

    private static function validateSplitColumn(GridColumn $column, GridLayoutSplit $layout): void
    {
        $below = true;
        foreach (Breakpoint::cases() as $breakpoint) {
            $below = $below && $breakpoint !== $layout->from;
            $rule = $column->rules[$breakpoint->value] ?? null;
            if (null === $rule) {
                continue;
            }
            if (null !== $rule->start) {
                throw new InvalidArgumentException('A start is not allowed in the split layout of a grid.');
            }
            if ($below && null !== $rule->width) {
                throw new InvalidArgumentException('In the split layout from "' . $layout->from->value . '" the columns stack below it, a width for "' . $breakpoint->value . '" is not allowed.');
            }
        }
    }

    private static function validateTile(GridColumn $column): void
    {
        foreach ($column->rules as $rule) {
            if (null !== $rule->width || null !== $rule->start) {
                throw new InvalidArgumentException('Width and start are not allowed in the tiles layout of a grid.');
            }
        }
    }

    private function validateSplitRow(GridLayoutSplit $layout): void
    {
        $widths = [];
        $visibilities = [];
        foreach ($this->columns as $column) {
            $widths[] = $column->resolve(static fn (GridColumnRule $rule): int|GridColumnWidth|null => $rule->width, GridColumnWidth::Fill);
            $visibilities[] = $column->resolve(static fn (GridColumnRule $rule): ?GridVisibility => $rule->visibility, GridVisibility::Visible);
        }

        $from = false;
        foreach (Breakpoint::cases() as $breakpoint) {
            $from = $from || $breakpoint === $layout->from;
            if (!$from) {
                continue;
            }

            $sum = 0;
            $flexible = false;
            foreach ($widths as $i => $width) {
                if (GridVisibility::Hidden === $visibilities[$i][$breakpoint->value]) {
                    continue;
                }
                $width = $width[$breakpoint->value];
                if (is_int($width)) {
                    $sum += $width;
                } else {
                    $flexible = true;
                }
            }

            if ($sum > GridLayoutColumns::COLUMNS || ($flexible && GridLayoutColumns::COLUMNS === $sum)) {
                throw new InvalidArgumentException('The widths of the visible columns of a split grid add up to ' . $sum . ($flexible ? ' besides auto or fill columns' : '') . ' from the breakpoint "' . $breakpoint->value . '" on, more than fit into one row.');
            }
        }
    }
}
