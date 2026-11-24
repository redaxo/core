<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Breadcrumb;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

use function Redaxo\Core\View\escape;

return static function (Breadcrumb $breadcrumb): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value ?>
    <nav <?= new HtmlAttributes(['id' => $breadcrumb->id, 'class' => ['rex-crumbs'], 'aria-label' => I18n::rawMsg('navigation_breadcrumb')]) ?>>
        <ol class="rex-crumbs-list">
            <?php if ($filled($breadcrumb->title)): ?>
                <li class="rex-crumbs-title"><?= Html::from($breadcrumb->title) ?></li>
            <?php endif ?>
            <?php foreach ($breadcrumb->items as $index => $item):
                // the last crumb is the current location
                $current = array_key_last($breadcrumb->items) === $index ? ' aria-current="location"' : '' ?>
                <?php if (isset($item['href']) && '' !== $item['href']): ?>
                    <li><a class="rex-crumbs-link" href="<?= escape($item['href']) ?>"<?= $current ?>><?= Html::from($item['title']) ?></a></li>
                <?php else: ?>
                    <li><span<?= $current ?>><?= Html::from($item['title']) ?></span></li>
                <?php endif ?>
            <?php endforeach ?>
        </ol>
    </nav>
<?php };
