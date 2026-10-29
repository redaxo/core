<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Tabs;
use Redaxo\Core\View\Component\TabsItem;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;

/**
 * Wrapper of the {@see Tabs} component for callers of the fragment.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

/** @var list<array<string, mixed>> $left */
$left = $this->getVar('left', []);
/** @var list<array<string, mixed>> $right */
$right = $this->getVar('right', []);
$id = Type::string($this->getVar('id', ''));

echo new Tabs(
    array_map(TabsItem::fromNavigation(...), $left),
    array_map(TabsItem::fromNavigation(...), $right),
    new HtmlAttributes(['id' => '' === $id ? null : $id]),
)->render();
