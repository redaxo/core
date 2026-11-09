<?php

use Redaxo\Core\View\Component\Breakpoint;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridGap;
use Redaxo\Core\View\Component\GridLayoutSplit;
use Redaxo\Core\View\Component\GridLayoutTiles;
use Redaxo\Core\View\Component\GridTileSize;

return static function (Grid $grid): void {
    if (!$grid->columns) {
        return;
    }

    $layout = $grid->layout;
    $attributes = $grid->attributes->with([
        'class' => ['rex-grid'],
        'data-layout' => match (true) {
            $layout instanceof GridLayoutSplit => 'split',
            $layout instanceof GridLayoutTiles => 'tiles',
            default => null,
        },
        'data-from' => $layout instanceof GridLayoutSplit && Breakpoint::Xs !== $layout->from ? $layout->from : null,
        'data-tile-size' => $layout instanceof GridLayoutTiles && GridTileSize::Medium !== $layout->size ? $layout->size : null,
        'data-gap' => GridGap::Default === $grid->gap ? null : $grid->gap,
        'data-subgrid' => $grid->subgrid,
    ]) ?>
    <div <?= $attributes ?>>
        <?php foreach ($grid->columns as $column): ?>
            <?= $column->render() ?>
        <?php endforeach ?>
    </div>
<?php };
