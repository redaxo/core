<?php

use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Component\TableRow;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

use function Redaxo\Core\View\escape;

return static function (Table $table): void {
    /** @return list<string> */
    $typeClass = static fn (ColumnType $type): array => ColumnType::Text === $type ? [] : ['rex-table-' . $type->value];
    $content = static fn (string|int|float|Renderable|null $value): string => match (true) {
        null === $value => '',
        $value instanceof Renderable => (string) $value->render(),
        is_string($value) => escape($value),
        default => (string) $value,
    };
    $filled = static fn (string|Renderable|null $value): bool => $value instanceof Renderable || (null !== $value && '' !== $value);

    // a header without label joins the previous one
    /** @var list<array{TableColumn, int}> $headers */
    $headers = [];
    foreach ($table->columns as $column) {
        $last = count($headers) - 1;
        if (null === $column->label && $last >= 0) {
            ++$headers[$last][1];
        } else {
            $headers[] = [$column, 1];
        }
    }
    $hasHead = [] !== array_filter($table->columns, static fn (TableColumn $column): bool => $filled($column->label));

    $cell = static function (TableColumn $column, mixed $row) use ($typeClass, $content): TableCell {
        $value = ($column->cell)($row);
        $cell = $value instanceof TableCell ? $value : new TableCell($value);
        $html = $content($cell->content);

        $url = '' === $html || null === $column->link ? null : ($column->link)($row);
        if (null !== $url) {
            $html = '<a ' . new HtmlAttributes(['href' => $url] + $column->linkAttributes + ['class' => 'rex-u-link-expanded'])->toString() . '>' . $html . '</a>';
        }

        // the header labels the cell where the rows become blocks on small screens
        $title = $column->title ?? (is_string($column->label) && '' !== $column->label && !in_array($column->type, [ColumnType::Icon, ColumnType::Action], true) ? $column->label : null);

        return new TableCell(Html::raw($html), $cell->colspan, $column->attributes->merge($cell->attributes)->with([
            'class' => $typeClass($column->type),
            'data-title' => $title,
        ]), $column->rowHeader || $cell->header);
    };
    $td = static function (TableCell $cell) use ($content): string {
        $tag = $cell->header ? 'th' : 'td';
        return '<' . $tag . ' ' . $cell->attributes->with(['colspan' => $cell->colspan > 1 ? $cell->colspan : null])->toString() . '>' . $content($cell->content) . '</' . $tag . '>';
    };
    ?>
    <table <?= $table->attributes->with(['class' => ['rex-table']]) ?>>
        <?php if ($filled($table->caption)): ?>
            <caption><?= Html::from($table->caption) ?></caption>
        <?php endif ?>
        <?php if ($hasHead): ?>
            <thead>
                <tr>
                    <?php foreach ($headers as [$column, $colspan]):
                        $label = (string) Html::from($column->label ?? '');
                        if (null !== $column->sortUrl) {
                            $icon = null === $column->sorted ? '' : ' <i class="rex-icon rex-icon-sort-' . $column->sorted . '" aria-hidden="true"></i>';
                            $label = '<a class="rex-u-link-expanded" href="' . escape($column->sortUrl) . '">' . $label . $icon . '</a>';
                        } ?>
                        <th <?= $column->attributes->with([
                            'class' => $typeClass($column->type),
                            'colspan' => $colspan > 1 ? $colspan : null,
                            'aria-sort' => match ($column->sorted) {
                                'asc' => 'ascending',
                                'desc' => 'descending',
                                null => null,
                            },
                        ]) ?>><?= Html::raw($label) ?></th>
                    <?php endforeach ?>
                </tr>
            </thead>
        <?php endif ?>
        <?php if ($filled($table->footer)): ?>
            <tfoot <?= new HtmlAttributes(['class' => ['rex-sticky-table-footer' => $table->stickyFooter]]) ?>><?= Html::from($table->footer) ?></tfoot>
        <?php endif ?>
        <tbody>
            <?php $empty = true ?>
            <?php /** @var mixed $row */ foreach ($table->rows as $row): $empty = false ?>
                <?php if ($row instanceof Html): ?>
                    <?= $row ?>
                    <?php continue ?>
                <?php endif ?>
                <?php if ($row instanceof TableRow): ?>
                    <tr <?= $row->attributes ?>><?= Html::raw(implode('', array_map($td, $row->cells))) ?></tr>
                    <?php continue ?>
                <?php endif ?>
                <tr <?= null === $table->rowAttributes ? '' : ($table->rowAttributes)($row) ?>>
                    <?php
                    // a cell spanning several columns replaces the cells of the following ones
                    $skip = 0;
                    foreach ($table->columns as $column) {
                        if ($skip > 0) {
                            --$skip;
                            continue;
                        }
                        $tableCell = $cell($column, $row);
                        $skip = $tableCell->colspan - 1;
                        echo $td($tableCell);
                    } ?>
                </tr>
            <?php endforeach ?>
            <?php if ($empty && $filled($table->empty)): ?>
                <tr><td colspan="<?= count($table->columns) ?>"><?= Html::from($table->empty) ?></td></tr>
            <?php endif ?>
        </tbody>
    </table>
<?php };
