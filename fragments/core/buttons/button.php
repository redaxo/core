<?php

use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

if (!isset($this->buttons)) {
    $this->buttons['button'] = $this->button;
}

foreach ($this->buttons as $button) {
    if (!isset($button['attributes'])) {
        $button['attributes'] = [];
    }
    if (!isset($button['attributes']['class'])) {
        $button['attributes']['class'] = [];
    }
    if (!in_array('btn', $button['attributes']['class'])) {
        $button['attributes']['class'] = array_merge(['btn'], $button['attributes']['class']);
    }

    if (!isset($button['label'])) {
        $button['label'] = '';
    }
    if (isset($button['hidden_label'])) {
        $button['label'] = '<span class="sr-only">' . $button['hidden_label'] . '</span>';
    }

    $icon = isset($button['icon']) ? '<i class="rex-icon rex-icon-' . $button['icon'] . '"></i>' : '';

    $tag = 'button';
    $href = '';
    if (isset($button['url'])) {
        $tag = 'a';
        $href = ' href="' . escape($button['url']) . '"';
    }
    echo '<' . $tag . $href . Str::buildAttributes($button['attributes']) . '>' . $icon . $button['label'] . '</' . $tag . '>';
}
