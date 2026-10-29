<?php

use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridAlign;
use Redaxo\Core\View\Component\GridColumn;
use Redaxo\Core\View\Component\GridColumnRule;
use Redaxo\Core\View\Html;

return static function (GridColumn $column): void {
    $attributes = $column->attributes->with([
        'data-span' => $column->tokens(static fn (GridColumnRule $rule) => $rule->width),
        'data-start' => $column->tokens(static fn (GridColumnRule $rule) => $rule->start),
        'data-order' => $column->tokens(static fn (GridColumnRule $rule) => $rule->order),
        'data-visibility' => $column->tokens(static fn (GridColumnRule $rule) => $rule->visibility),
        'data-align-x' => GridAlign::Stretch === $column->alignX ? null : $column->alignX,
        'data-align-y' => GridAlign::Stretch === $column->alignY ? null : $column->alignY,
    ]);

    // a subgrid is the column itself, so its columns are on the tracks of the outer grid
    if ($column->content instanceof Grid && $column->content->subgrid) {
        echo $column->content->withAttributes($attributes)->render();

        return;
    }

    // the single child aligns horizontally in the split layout, where `justify-self` has no effect
    $content = GridAlign::Stretch === $column->alignX ? Html::from($column->content) : '<div>' . (string) Html::from($column->content) . '</div>';
    $attributes = $attributes->toString() ?>
    <div<?= '' === $attributes ? '' : ' ' . $attributes ?>><?= $content ?></div>
<?php };
