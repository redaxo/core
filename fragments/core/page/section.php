<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

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
$variant = SectionVariant::tryFrom((string) $this->getVar('class', '')) ?? SectionVariant::Default;

// `before` and `after` stand outside the section, e.g. a message above the form of a slice
echo $raw('before');
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
)->render();
echo $raw('after');
