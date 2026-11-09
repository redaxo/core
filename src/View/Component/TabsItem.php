<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

use function is_array;
use function is_int;
use function is_string;
use function Redaxo\Core\View\escape;

/**
 * Item of {@see Tabs}.
 *
 * ```php
 * new TabsItem('Settings', Url::backendPage('my_addon/settings'), active: true, icon: new Icon('system'), children: [
 *     new TabsItem('General', Url::backendPage('my_addon/settings/general'), active: true),
 *     new TabsItem('Mail', Url::backendPage('my_addon/settings/mail')),
 * ]);
 * ```
 */
final readonly class TabsItem
{
    /**
     * @param string|Renderable $label plain text is escaped, use {@see Html} for markup
     * @param string|null $href without it the item is shown as text
     * @param list<TabsItem> $children subnavigation, shown below the tabs while the item is active
     * @param HtmlAttributes $attributes further attributes of the `<li>`
     * @param HtmlAttributes $linkAttributes further attributes of the link, e.g. `target`
     */
    public function __construct(
        public string|Renderable $label,
        public ?string $href = null,
        public bool $active = false,
        public ?Icon $icon = null,
        public array $children = [],
        public HtmlAttributes $attributes = new HtmlAttributes(),
        public HtmlAttributes $linkAttributes = new HtmlAttributes(),
    ) {}

    /**
     * Item from an array of the {@see Navigation} of the backend: `title` (HTML), and optionally `href`, `active`,
     * `icon` (CSS classes), `itemClasses`, `itemAttr`, `linkClasses`, `linkAttr` and `children`.
     *
     * @param array<array-key, mixed> $item
     *
     * @internal
     */
    public static function fromNavigation(array $item): self
    {
        /** @var mixed $title */
        $title = $item['title'] ?? null;
        if (!is_string($title)) {
            throw new InvalidArgumentException('A navigation item needs a "title".');
        }
        /** @var mixed $icon */
        $icon = $item['icon'] ?? null;
        /** @var mixed $href */
        $href = $item['href'] ?? null;
        /** @var mixed $children */
        $children = $item['children'] ?? null;
        /** @var mixed $itemAttr */
        $itemAttr = $item['itemAttr'] ?? null;

        $childItems = [];
        /** @var mixed $child */
        foreach (is_array($children) ? $children : [] as $child) {
            if (is_array($child)) {
                $childItems[] = self::fromNavigation($child);
            }
        }

        $itemAttributes = self::attributes($itemAttr);
        $itemAttributes['class'] = [...self::classes($item['itemClasses'] ?? []), ...self::classes(is_array($itemAttr) ? $itemAttr['class'] ?? [] : [])];
        // empty link attributes stay, e.g. the valueless `data-rex-overlay` of popup pages
        $linkAttributes = self::attributes($item['linkAttr'] ?? [], true);
        $linkAttributes['class'] = self::classes($item['linkClasses'] ?? []);

        return new self(
            Html::raw((is_string($icon) && '' !== $icon ? '<i class="' . escape($icon) . '" aria-hidden="true"></i> ' : '') . $title),
            is_string($href) && '' !== $href ? $href : null,
            (bool) ($item['active'] ?? false),
            children: $childItems,
            attributes: new HtmlAttributes($itemAttributes),
            linkAttributes: new HtmlAttributes($linkAttributes),
        );
    }

    /** @return array<literal-string, string|int|true> */
    private static function attributes(mixed $attributes, bool $keepEmpty = false): array
    {
        $result = [];
        foreach (is_array($attributes) ? $attributes : [] as $name => $value) {
            if (!is_string($name) || 'class' === $name || !is_string($value) && !is_int($value)) {
                continue;
            }
            if ('' === $value) {
                if (!$keepEmpty) {
                    continue;
                }
                $value = true;
            }
            /** @var literal-string $name */
            $result[$name] = $value;
        }

        return $result;
    }

    /** @return list<string> */
    private static function classes(mixed $classes): array
    {
        $result = [];
        /** @var mixed $class */
        foreach (is_array($classes) ? $classes : [$classes] as $class) {
            if (is_string($class) && '' !== $class) {
                $result[] = $class;
            }
        }

        return $result;
    }
}
