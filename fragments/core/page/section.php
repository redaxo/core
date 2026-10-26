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

$raw = fn (string $name): Html => Html::raw((string) $this->getVar($name, ''));
$class = (string) $this->getVar('class', '');
$variant = SectionVariant::tryFrom($class) ?? SectionVariant::Default;

echo new Section(
    title: $raw('title'),
    body: $raw('body'),
    content: $raw('content'),
    footer: Html::raw(Type::string($this->getVar('footer', '')) . Type::string($this->getVar('buttons', ''))),
    options: $raw('options'),
    header: $raw('heading'),
    variant: $variant,
    collapsible: (bool) $this->getVar('collapse', false),
    collapsed: (bool) $this->getVar('collapsed', false),
    attributes: new HtmlAttributes(['class' => '' === $class || $variant->value === $class ? [] : ['panel-' . $class]]),
)->render();
