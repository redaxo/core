<?php

use Redaxo\Core\View\Component\Breakpoint as B;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridAlign;
use Redaxo\Core\View\Component\GridColumn as C;
use Redaxo\Core\View\Component\GridColumnRule as R;
use Redaxo\Core\View\Component\GridColumnWidth as W;
use Redaxo\Core\View\Component\GridGap;
use Redaxo\Core\View\Component\GridLayoutSplit;
use Redaxo\Core\View\Component\GridLayoutTiles;
use Redaxo\Core\View\Component\GridOrder;
use Redaxo\Core\View\Component\GridTileSize;
use Redaxo\Core\View\Component\GridVisibility;
use Redaxo\Core\View\Html;

/*
 * Generates the visual test fixture of the grid (fixtures/grid.html) from the component:
 * php .tools/visual-tests/generate-grid-fixture.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$box = static fn (string $label): Html => Html::raw('<div class="demo">' . htmlspecialchars($label) . '</div>');
$tall = static fn (string $label): Html => Html::raw('<div class="demo demo-tall">' . htmlspecialchars($label) . '</div>');

$responsive = [
    'Spans: md:8 xl:7 / md:4 xl:5 / 6 sm:4 lg:3' => new Grid([
        new C($box('md:8 xl:7'), [new R(B::Md, width: 8), new R(B::Xl, width: 7)]),
        new C($box('md:4 xl:5'), [new R(B::Md, width: 4), new R(B::Xl, width: 5)]),
        new C($box('6 sm:4 lg:3'), [new R(B::Xs, width: 6), new R(B::Sm, width: 4), new R(B::Lg, width: 3)]),
        new C($box('6 sm:8 lg:9'), [new R(B::Xs, width: 6), new R(B::Sm, width: 8), new R(B::Lg, width: 9)]),
    ]),
    'Start: md:6 start md:4, lg:4 start lg:9' => new Grid([
        new C($box('md:6 start md:4'), [new R(B::Md, width: 6, start: 4)]),
        new C($box('lg:4 start lg:9'), [new R(B::Lg, width: 4, start: 9)]),
    ]),
    'Order and visibility: second is first below md, third hidden below md' => new Grid([
        new C($box('1'), [new R(B::Md, width: 4)]),
        new C($box('2: first md:source'), [new R(B::Xs, order: GridOrder::First), new R(B::Md, width: 4, order: GridOrder::Source)]),
        new C($box('3: hidden md:visible'), [new R(B::Xs, visibility: GridVisibility::Hidden), new R(B::Md, width: 4, visibility: GridVisibility::Visible)]),
    ]),
    'Split from md: five equal columns' => new Grid(array_map($box, ['1', '2', '3', '4', '5']), layout: new GridLayoutSplit(B::Md)),
    'Split from sm: md:3 and fill' => new Grid([new C($box('md:3'), [new R(B::Md, width: 3)]), $box('fill')], layout: new GridLayoutSplit(B::Sm)),
];

$fixed = [
    'Split: fill and auto, aligned to the end' => new Grid([
        Html::raw('<input class="rex-input" aria-label="Field" value="fill">'),
        new C(Html::raw('<span class="rex-button-toolbar"><button type="button" class="rex-button">auto</button><button type="button" class="rex-button" data-variant="primary">Save</button></span>'), [new R(B::Xs, width: W::Auto)], alignY: GridAlign::End),
    ], layout: new GridLayoutSplit()),
    'Split: 4 and fill and auto' => new Grid([new C($box('4'), [new R(B::Xs, width: 4)]), $box('fill'), new C($box('auto'), [new R(B::Xs, width: W::Auto)])], layout: new GridLayoutSplit()),
    'Split: alignX start, center, end' => new Grid([new C($box('start'), alignX: GridAlign::Start), new C($box('center'), alignX: GridAlign::Center), new C($box('end'), alignX: GridAlign::End)], layout: new GridLayoutSplit()),
    'Columns: alignX and alignY' => new Grid([
        new C($tall('tall'), [new R(B::Xs, width: 3)]),
        new C($box('x start, y start'), [new R(B::Xs, width: 3)], alignX: GridAlign::Start, alignY: GridAlign::Start),
        new C($box('x center, y center'), [new R(B::Xs, width: 3)], alignX: GridAlign::Center, alignY: GridAlign::Center),
        new C($box('x end, y end'), [new R(B::Xs, width: 3)], alignX: GridAlign::End, alignY: GridAlign::End),
    ]),
    'Tiles small' => new Grid(array_map($box, range(1, 7)), layout: new GridLayoutTiles(GridTileSize::Small)),
    'Tiles medium' => new Grid(array_map($box, range(1, 5)), layout: new GridLayoutTiles()),
    'Tiles large' => new Grid(array_map($box, range(1, 3)), layout: new GridLayoutTiles(GridTileSize::Large)),
    'Gap none, small, large' => new Grid([
        new Grid(array_map($box, ['none', 'none', 'none', 'none']), gap: GridGap::None, layout: new GridLayoutSplit()),
        new Grid(array_map($box, ['sm', 'sm', 'sm', 'sm']), gap: GridGap::Small, layout: new GridLayoutSplit()),
        new Grid(array_map($box, ['lg', 'lg', 'lg', 'lg']), gap: GridGap::Large, layout: new GridLayoutSplit()),
    ]),
    'Columns wrap with the row gap' => new Grid(array_map(static fn ($i) => new C($box((string) $i), [new R(B::Xs, width: 4)]), range(1, 6))),
    'Subgrid: inner columns on the outer tracks' => new Grid([
        new C($box('3'), [new R(B::Xs, width: 3)]),
        new C(new Grid([new C($box('inner 4'), [new R(B::Xs, width: 4)]), new C($box('inner 5'), [new R(B::Xs, width: 5)])], subgrid: true), [new R(B::Xs, width: 9)]),
        new C($box('3'), [new R(B::Xs, width: 3)]),
        new C($box('4'), [new R(B::Xs, width: 4)]),
        new C($box('5'), [new R(B::Xs, width: 5)]),
    ]),
];

$html = static fn (Grid $grid): string => (string) $grid->render();

ob_start() ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Grid</title>
    <!-- Visual test fixture of the grid (components/grid.css) and the Bootstrap 3 compat grid; generated from
         Redaxo\Core\View\Component\Grid, so it shows the markup the component renders. Grids query their own
         width, so the fixed widths below stand for a sidebar, a dialog, an overlay and the main area. -->
    <link rel="stylesheet" href="/assets/core/css/backend.css">
    <link rel="stylesheet" href="/assets/core/css/bootstrap-compat.css">
    <style>
        body { padding: 20px; }
        h2 { margin: 30px 0 10px; font-size: 1.125rem; }
        h3 { margin: 15px 0 5px; font-size: 0.875rem; font-weight: 400; }
        .demo { padding: 6px 10px; border: 1px solid var(--rex-color-border-strong); background: var(--rex-color-accent-subtle); }
        .demo-tall { min-height: 90px; }
        .frame { outline: 1px dashed var(--rex-color-border-strong); }
        <?php foreach ([400, 600, 800, 1000, 1200] as $width): ?>
        .w<?= $width ?> { width: <?= $width ?>px; }
        <?php endforeach ?>
    </style>
</head>
<body>
<main>
    <h1>Grid</h1>
    <?php foreach ($responsive as $title => $grid): ?>
        <h2><?= htmlspecialchars($title) ?></h2>
        <?php foreach ([400, 600, 800, 1000, 1200] as $width): ?>
            <h3><?= $width ?>px</h3>
            <div class="frame w<?= $width ?>"><?= $html($grid) ?></div>
        <?php endforeach ?>
    <?php endforeach ?>
    <?php foreach ($fixed as $title => $grid): ?>
        <h2><?= htmlspecialchars($title) ?></h2>
        <div class="frame w1000"><?= $html($grid) ?></div>
    <?php endforeach ?>

    <h2>Bootstrap 3 compat (viewport 1280px)</h2>
    <div class="container-fluid frame w1200">
        <div class="row">
            <div class="col-md-4 col-md-offset-2"><div class="demo">col-md-4 col-md-offset-2</div></div>
            <div class="col-md-4"><div class="demo">col-md-4</div></div>
        </div>
        <div class="row">
            <div class="col-md-8 col-md-push-4"><div class="demo">col-md-8 col-md-push-4 (first in source)</div></div>
            <div class="col-md-4 col-md-pull-8"><div class="demo">col-md-4 col-md-pull-8</div></div>
        </div>
        <div class="row">
            <div class="col-xs-6 hidden-lg"><div class="demo">hidden-lg (not shown)</div></div>
            <div class="col-xs-6 visible-lg-block"><div class="demo">visible-lg-block</div></div>
            <div class="col-xs-6 hidden-xs hidden-sm"><div class="demo">hidden-xs hidden-sm</div></div>
        </div>
    </div>
</main>
</body>
</html>
<?php
$out = ob_get_clean();
$out = preg_replace('/^\s*\n/m', '', $out);
file_put_contents(__DIR__ . '/fixtures/grid.html', $out);
