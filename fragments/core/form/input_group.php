<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';

foreach ($this->elements as $element) {
    $id = isset($element['id']) && '' != $element['id'] ? ' id="' . $element['id'] . '"' : '';
    $field = isset($element['field']) && '' != $element['field'] ? $element['field'] : '';
    $leftSide = $element['left'] ?? '';
    $rightSide = $element['right'] ?? '';
    // special for bootstrap-select
    $before = $element['before'] ?? '';
    $after = $element['after'] ?? '';

    $classes = '';

    if (isset($element['class']) && '' != $element['class']) {
        $classes .= ' ' . $element['class'];
    }

    if ('' != $leftSide) {
        $class = 'rex-input-group-text';
        if (preg_match('@class=[\'"](?:btn|rex-button)\b@', $leftSide)) {
            $class = 'rex-input-group-actions';
        }

        $field = '<span class="' . $class . '">' . $leftSide . '</span>' . $field;
    }

    if ('' != $rightSide) {
        $class = 'rex-input-group-text';
        if (preg_match('@class=[\'"](?:btn|rex-button)\b@', $rightSide)) {
            $class = 'rex-input-group-actions';
        }

        $field = $field . '<span class="' . $class . '">' . $rightSide . '</span>';
    }

    $out .= '<div class="rex-input-group' . $classes . '"' . $id . '>' . $before . $field . $after . '</div>';
}

echo $out;
