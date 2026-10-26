<?php

use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Tabs;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (Tabs $tabs): void {
    $item = static function (array $item, string $block, bool $end = false): Html {
        $attributes = static function (mixed $attributes): array {
            $result = [];
            /** @var mixed $value */
            foreach ((array) $attributes as $name => $value) {
                if (is_string($name) && (is_string($value) || is_int($value)) && '' !== $value) {
                    $result[$name] = $value;
                }
            }

            return $result;
        };
        $classes = static fn (mixed $classes): array => array_values(array_filter((array) $classes, static fn (mixed $class): bool => is_string($class) && '' !== $class));

        $rawItemAttr = (array) ($item['itemAttr'] ?? []);
        $itemAttr = $attributes($rawItemAttr);
        $itemAttr['class'] = [
            $block . '__item',
            ...$classes($item['itemClasses'] ?? []),
            ...$classes($rawItemAttr['class'] ?? []),
            ...(($item['active'] ?? false) ? ['is-active'] : []),
            ...($end ? [$block . '__item--end'] : []),
        ];

        $label = ('' !== ($icon = Type::string($item['icon'] ?? '')) ? '<i class="' . escape($icon) . '"></i> ' : '') . Type::string($item['title']);
        $href = Type::string($item['href'] ?? '');
        if ('' === $href) {
            return Html::raw('<li' . Str::buildAttributes($itemAttr) . '><span class="' . $block . '__link">' . $label . '</span></li>');
        }

        $linkAttr = $attributes($item['linkAttr'] ?? []);
        $linkAttr['class'] = [$block . '__link', ...$classes($item['linkClasses'] ?? [])];

        return Html::raw('<li' . Str::buildAttributes($itemAttr) . '><a ' . new HtmlAttributes(['href' => $href])->toString() . Str::buildAttributes($linkAttr) . '>' . $label . '</a></li>');
    };

    $children = [];
    foreach ([...$tabs->items, ...$tabs->endItems] as $tab) {
        if (($tab['active'] ?? false) && is_array($tab['children'] ?? null) && $tab['children']) {
            /** @var list<array<string, mixed>> $children */
            $children = $tab['children'];
        }
    }

    if (!$tabs->items && !$tabs->endItems && !$children) {
        return;
    } ?>
    <nav <?= new HtmlAttributes(['id' => $tabs->id, 'class' => ['rex-pagenav']]) ?>>
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
