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
        $class = 'input-group-addon';
        if (preg_match('@class=[\'|"]btn[^"\']@', $leftSide)) {
            $class = 'input-group-btn';
        }

        $field = '<span class="' . $class . '">' . $leftSide . '</span>' . $field;
    }

    if ('' != $rightSide) {
        $class = 'input-group-addon';
        if (preg_match('@class=[\'|"]btn[^"\']@', $rightSide)) {
            $class = 'input-group-btn';
        }

        $field = $field . '<span class="' . $class . '">' . $rightSide . '</span>';
    }

    $out .= '<div class="input-group' . $classes . '"' . $id . '>' . $before . $field . $after . '</div>';
}

echo $out;
