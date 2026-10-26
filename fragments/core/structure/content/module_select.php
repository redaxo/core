<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Dropdown;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

/**
 * Selection of the module for a new slice.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

/** @var list<array{key: string, title: string, href: string}> $items */
$items = $this->getVar('items', []);

$links = [];
foreach ($items as $item) {
    /** @psalm-taint-escape html */ // the titles are escaped by the caller
    $title = $item['title'];
    $links[] = ['title' => Html::raw($title), 'href' => $item['href']];
}

echo new Dropdown(
    $links,
    label: Type::string($this->getVar('button_label', '')),
    block: (bool) $this->getVar('block', false),
)->render();
