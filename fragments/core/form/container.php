<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';

foreach ($this->elements as $element) {
    $id = isset($element['id']) && '' != $element['id'] ? ' id="' . $element['id'] . '"' : '';
    $label = isset($element['label']) && '' != $element['label'] ? '<dt>' . $element['label'] . '</dt>' : '';
    $field = isset($element['field']) && '' != $element['field'] ? $element['field'] : '';

    $before = $element['before'] ?? '';
    $after = $element['after'] ?? '';

    $header = $element['header'] ?? '';
    $footer = $element['footer'] ?? '';

    $note = isset($element['note']) && '' != $element['note'] ? '<p class="help-block rex-note">' . $element['note'] . '</p>' : '';

    $classes = '';

    $error = '';
    if (isset($element['error']) && '' != $element['error']) {
        $classes .= ' has-error';
        $error = '<p class="rex-form-error">' . $element['error'] . '</p>';
    }
    if (isset($element['required']) && $element['required']) {
        $classes .= ' rex-is-required';
    }
    if (isset($element['class']) && '' != $element['class']) {
        $classes .= ' ' . $element['class'];
    }

    $out .= $header;
    $out .= '<div class="rex-form-group rex-form-container form-group' . $classes . '"' . $id . '>';
    $out .= $label;
    $out .= '<div class="rex-form-container-field">' . $before . $field . $after . $note . $error . '</div>';
    $out .= '</div>';
    $out .= $footer;
}

echo $out;
