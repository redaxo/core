<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonGroup;
use Redaxo\Core\View\Component\ButtonVariant;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/**
 * Buttons given as arrays (`label`, `url`, `icon`, `hidden_label`, `variant`, `attributes`), e.g. by the slice menu,
 * as a {@see ButtonGroup}. `variant` is a {@see ButtonVariant} value; without it, Bootstrap 3 classes like `btn-save`
 * of older items still pick the matching variant. A button without `url` submits its form unless `attributes` give
 * another `type`.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

/** @var list<array<string, mixed>> $buttons */
$buttons = $this->getVar('buttons', []);

$legacyVariants = [
    'btn-save' => ButtonVariant::Success, 'btn-apply' => ButtonVariant::Success, 'btn-edit' => ButtonVariant::Success,
    'btn-success' => ButtonVariant::Success, 'btn-primary' => ButtonVariant::Primary, 'btn-send' => ButtonVariant::Primary,
    'btn-select' => ButtonVariant::Primary, 'btn-delete' => ButtonVariant::Danger, 'btn-danger' => ButtonVariant::Danger,
    'btn-warning' => ButtonVariant::Warning,
];

echo new ButtonGroup(
    array_map(static function (array $button) use ($legacyVariants): Button {
        $attributes = [];
        foreach ((array) ($button['attributes'] ?? []) as $name => $value) {
            /** @var literal-string $name */
            $attributes[(string) $name] = is_array($value) ? array_values(array_map(Type::string(...), $value)) : Type::string($value);
        }
        $hidden = isset($button['hidden_label']);

        // like the <button> of the older fragment, a button without URL submits its form unless it says otherwise
        $type = Type::string($attributes['type'] ?? 'submit');
        unset($attributes['type']);

        $variant = ButtonVariant::tryFrom(Type::string($button['variant'] ?? ''));
        foreach ((array) ($attributes['class'] ?? []) as $class) {
            $variant ??= $legacyVariants[$class] ?? null;
        }

        return new Button(
            Html::raw(Type::string($hidden ? $button['hidden_label'] : ($button['label'] ?? ''))),
            isset($button['url']) ? Type::string($button['url']) : null,
            isset($button['icon']) ? Type::string($button['icon']) : null,
            variant: $variant ?? ButtonVariant::Secondary,
            hiddenLabel: $hidden,
            type: $type,
            attributes: new HtmlAttributes($attributes),
        );
    }, $buttons),
    vertical: (bool) $this->getVar('vertical', false),
    small: 'xs' === $this->getVar('size'),
)->render();
