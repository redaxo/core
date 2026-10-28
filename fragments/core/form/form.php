<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';

foreach ($this->elements as $element) {
    $id = isset($element['id']) && '' != $element['id'] ? ' id="' . $element['id'] . '"' : '';
    $label = $element['label'] ?? '';
    $field = $element['field'] ?? '';

    $before = $element['before'] ?? '';
    $after = $element['after'] ?? '';

    $header = $element['header'] ?? '';
    $footer = $element['footer'] ?? '';

    // note and error describe the field the label points to
    $target = preg_match('/\bfor="([^"]+)"/', $label, $match) ? $match[1] : 'rex-field-' . bin2hex(random_bytes(4));
    $describedBy = [];

    $error = '';
    if (isset($element['error']) && '' != $element['error']) {
        $error = '<p class="rex-field-error" id="' . $target . '-error">' . $element['error'] . '</p>';
        $describedBy[] = $target . '-error';
    }

    $note = '';
    if (isset($element['note']) && '' != $element['note']) {
        $note = '<p class="rex-field-note" id="' . $target . '-note">' . $element['note'] . '</p>';
        $describedBy[] = $target . '-note';
    }

    if ($describedBy) {
        $attributes = ' aria-describedby="' . implode(' ', $describedBy) . '"' . ('' === $error ? '' : ' aria-invalid="true"');
        $field = preg_replace('/(<(?:input|select|textarea)\b[^>]*\bid="' . preg_quote($target, '/') . '")/', '$1' . $attributes, $field, 1) ?? $field;
    }

    $classes = 'rex-field';
    if (isset($element['class']) && '' != $element['class']) {
        $classes .= ' ' . $element['class'];
    }
    $layout = isset($element['vertical']) && true === $element['vertical'] ? ' data-layout="vertical"' : '';

    $out .= $header;
    $out .= '<div class="' . $classes . '"' . $layout . $id . '>';
    $out .= '' == $label ? '' : '<div class="rex-field-label">' . $label . '</div>';
    $out .= '<div class="rex-field-control">' . $before . $field . $after . $note . $error . '</div>';
    $out .= '</div>';
    $out .= $footer;
}

echo $out;
