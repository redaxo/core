<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';

foreach ($this->elements as $element) {
    $field = $element['field'] ?? '';
    $functionButtons = $element['functionButtons'] ?? '';
    $before = $element['before'] ?? '';
    $after = $element['after'] ?? '';

    $out .= $before;

    $out .= '<div class="input-group">';

    $out .= $field;

    if ('' != $functionButtons) {
        $out .= '<span class="input-group-btn">' . $functionButtons . '</span>';
    }

    $out .= '</div>';

    $out .= $after;
}

echo $out;
