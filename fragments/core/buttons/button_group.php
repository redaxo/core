<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonGroup;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/**
 * Buttons given as arrays (`label`, `url`, `icon`, `hidden_label`, `attributes`), e.g. by the slice menu, as a
 * {@see ButtonGroup}.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

/** @var list<array<string, mixed>> $buttons */
$buttons = $this->getVar('buttons', []);

echo new ButtonGroup(
    array_map(static function (array $button): Button {
        $attributes = [];
        foreach ((array) ($button['attributes'] ?? []) as $name => $value) {
            /** @var literal-string $name */
            $attributes[(string) $name] = is_array($value) ? array_values(array_map(Type::string(...), $value)) : Type::string($value);
        }
        $hidden = isset($button['hidden_label']);

        return new Button(
            Html::raw(Type::string($hidden ? $button['hidden_label'] : ($button['label'] ?? ''))),
            isset($button['url']) ? Type::string($button['url']) : null,
            isset($button['icon']) ? Type::string($button['icon']) : null,
            variant: null,
            hiddenLabel: $hidden,
            attributes: new HtmlAttributes($attributes),
        );
    }, $buttons),
    vertical: (bool) $this->getVar('vertical', false),
    small: 'xs' === $this->getVar('size'),
)->render();
