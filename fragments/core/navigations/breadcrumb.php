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

echo new Breadcrumb(
    array_map(static fn (array $item): array => ['title' => Html::raw($item['title'] ?? ''), 'href' => $item['href'] ?? ''], $items),
    Html::raw((string) $this->getVar('title', '')),
    '' === $id ? null : $id,
)->render();
