<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Breadcrumb;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

/**
 * Wrapper of the {@see Breadcrumb} component for callers of the fragment; titles are not escaped.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

/** @var list<array{title?: string, href?: string}> $items */
$items = $this->getVar('items', []);
$id = Type::string($this->getVar('id', ''));

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

$crumbs = [];
foreach ($items as $item) {
    /** @psalm-taint-escape html */
    $title = $item['title'] ?? '';
    $crumbs[] = ['title' => Html::raw($title), 'href' => $item['href'] ?? ''];
}

echo new Breadcrumb(
    $crumbs,
    $raw('title'),
    '' === $id ? null : $id,
)->render();
