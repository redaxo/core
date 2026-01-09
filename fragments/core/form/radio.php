<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$out = '';
// Gruppierte Radios werden nochmals via form/form.php geparsed
// Bsp. Radios in der Form
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

    $note = isset($element['note']) && '' != $element['note'] ? '<span class="help-block rex-note">' . $element['note'] . '</span>' : '';
    $highlight = $element['highlight'] ?? false;

    if ('' != $field) {
        $match = $highlight ? '<mark>$2</mark>' : '$2';
        $label = preg_replace('@(<label\b[^>]*>)(.*?)(</label>)@', '$1' . $field . $match . $note . '$3', $label);
    }

    $classes = '';

    $error = '';
    if (isset($element['error']) && '' != $element['error']) {
        $classes .= ' has-error';
        $error = '<dd class="rex-form-error">' . $element['error'] . '</dd>';
    }
    if (isset($element['required']) && $element['required']) {
        $classes .= ' rex-is-required';
    }

    $class = $inline ? '-inline' : '';
    $grouped = $inline ? true : $grouped;

    if ($grouped) {
        $out .= '<div class="radio' . $class . '"' . $id . '>';
        $out .= $label;
        $out .= '</div>';
    } else {
        $out .= $header;
        $out .= '<dl class="rex-form-group form-group' . $classes . '">';
        $out .= '<dd>';
        $out .= $before;
        $out .= '<div class="radio' . $class . '"' . $id . '>';
        $out .= $label;
        $out .= '</div>';
        $out .= $after;
        $out .= '</dd>';
        $out .= $error;
        $out .= '</dl>';
        $out .= $footer;
    }
}

echo $out;
