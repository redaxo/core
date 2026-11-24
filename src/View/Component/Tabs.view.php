<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Tabs;
use Redaxo\Core\View\Component\TabsItem;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

return static function (Tabs $tabs): void {
    $item = static function (TabsItem $item, string $block, bool $end = false): Html {
        $label = (null === $item->icon ? '' : trim((string) $item->icon->render()) . ' ') . (string) Html::from($item->label);
        // items keep an alignment of their own, e.g. from `Page::setItemAttr('data-align', 'end')`
        $attributes = $item->attributes->with(['class' => [$block . '-item'], ...$end ? ['data-align' => 'end'] : []])->toString();
        if (null === $item->href) {
            return Html::raw('<li ' . $attributes . '><span class="' . $block . '-link">' . $label . '</span></li>');
        }
        $linkAttributes = $item->linkAttributes->with(['href' => $item->href, 'class' => [$block . '-link'], 'aria-current' => $item->active ? 'page' : null])->toString();

        return Html::raw('<li ' . $attributes . '><a ' . $linkAttributes . '>' . $label . '</a></li>');
    };

    $children = [];
    foreach ([...$tabs->items, ...$tabs->endItems] as $tab) {
        if ($tab->active && $tab->children) {
            $children = $tab->children;
        }
    }

    if (!$tabs->items && !$tabs->endItems && !$children) {
        return;
    }
    $attributes = new HtmlAttributes(['aria-label' => I18n::rawMsg('navigation_subpages')])->merge($tabs->attributes)->with(['class' => ['rex-pagenav']]) ?>
    <nav <?= $attributes ?>>
        <?php if ($tabs->items || $tabs->endItems): ?>
            <ul class="rex-tabs">
                <?php foreach ($tabs->items as $tab): ?>
                    <?= $item($tab, 'rex-tabs') ?>
                <?php endforeach ?>
                <?php foreach ($tabs->endItems as $tab): ?>
                    <?= $item($tab, 'rex-tabs', true) ?>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
        <?php if ($children): ?>
            <ul class="rex-subnav">
                <?php foreach ($children as $child): ?>
                    <?= $item($child, 'rex-subnav') ?>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </nav>
<?php };
