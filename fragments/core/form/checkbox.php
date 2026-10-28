<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';
// Gruppierte Checkboxen werden nochmals via form/form.php geparsed
// Bsp. Checkboxen in der Form
$grouped = $this->grouped ?? false;
$inline = $this->inline ?? false;

foreach ($this->elements as $element) {
    $id = isset($element['id']) && '' != $element['id'] ? ' id="' . $element['id'] . '"' : '';
    $label = $element['label'] ?? '<label></label>';
    $field = $element['field'] ?? '';

    $before = $element['before'] ?? '';
    $after = $element['after'] ?? '';

    $header = $element['header'] ?? '';
    $footer = $element['footer'] ?? '';

    $note = isset($element['note']) && '' != $element['note'] ? '<span class="rex-field-note">' . $element['note'] . '</span>' : '';
    $highlight = $element['highlight'] ?? false;

    if ('' != $field) {
        $match = $highlight ? '<mark>$2</mark>' : '$2';
        $label = preg_replace('@(<label\b[^>]*>)(.*?)(</label>)@', '$1' . $field . $match . $note . '$3', $label);
    }

    $classes = '';

    $error = '';
    if (isset($element['error']) && '' != $element['error']) {
        $error = '<p class="rex-field-error">' . $element['error'] . '</p>';
    }
    if (isset($element['class']) && '' != $element['class']) {
        $classes .= ' ' . $element['class'];
    }

    $class = $inline ? '-inline' : '';

    if ($grouped) {
        $out .= '<div class="checkbox' . $class . '"' . $id . '>';
        $out .= $label;
        $out .= '</div>';
    } else {
        $out .= $header;
        $out .= '<div class="rex-field' . $classes . '">';
        $out .= '<div class="rex-field-control">';
        $out .= $before;
        $out .= '<div class="checkbox' . $class . '"' . $id . '>';
        $out .= $label;
        $out .= '</div>';
        $out .= $after;
        $out .= $error;
        $out .= '</div>';
        $out .= '</div>';
        $out .= $footer;
    }
}

echo $out;
