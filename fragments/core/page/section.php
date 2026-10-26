<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/**
 * Wrapper of the {@see Section} component for callers of the fragment.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

// the variables of the fragment hold markup the caller vouches for
$raw = function (string ...$names): Html {
    $html = '';
    foreach ($names as $name) {
        $html .= Type::string($this->getVar($name, ''));
    }

    /** @psalm-taint-escape html */
    $html = $html;

    return Html::raw($html);
};
$class = (string) $this->getVar('class', '');
$variant = SectionVariant::tryFrom($class) ?? SectionVariant::Default;

echo new Section(
    title: $raw('title'),
    body: $raw('body'),
    content: $raw('content'),
    footer: $raw('footer', 'buttons'),
    options: $raw('options'),
    header: $raw('heading'),
    variant: $variant,
    collapsible: (bool) $this->getVar('collapse', false),
    collapsed: (bool) $this->getVar('collapsed', false),
    attributes: new HtmlAttributes(['class' => '' === $class || $variant->value === $class ? [] : ['panel-' . $class]]),
)->render();
