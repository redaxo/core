<?php

use Redaxo\Core\View\Component\Breadcrumb;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

use function Redaxo\Core\View\escape;

return static function (Breadcrumb $breadcrumb): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value ?>
    <nav <?= new HtmlAttributes(['id' => $breadcrumb->id, 'class' => ['rex-crumbs']]) ?>>
        <ol class="rex-crumbs__list">
            <?php if ($filled($breadcrumb->title)): ?>
                <li class="rex-crumbs__title"><?= Html::from($breadcrumb->title) ?></li>
            <?php endif ?>
            <?php foreach ($breadcrumb->items as $item): ?>
                <?php if (isset($item['href']) && '' !== $item['href']): ?>
                    <li><a class="rex-crumbs__link" href="<?= escape($item['href']) ?>"><?= Html::from($item['title']) ?></a></li>
                <?php else: ?>
                    <li><?= Html::from($item['title']) ?></li>
                <?php endif ?>
            <?php endforeach ?>
        </ol>
    </nav>
<?php };
