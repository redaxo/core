<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$id = '';
$class = escape((string) $this->getVar('class', 'rex-input-group'));
// the placeholder is plain text and names the field as well
$label = escape((string) $this->getVar('placeholder', I18n::rawMsg('search_placeholder')));
$placeholder = ' placeholder="' . $label . '" aria-label="' . $label . '"';
$autofocus = '';
if (isset($this->autofocus) && $this->autofocus) {
    $autofocus = ' autofocus ';
}
$value = (string) $this->getVar('value', '');
if ('' !== $value) {
    $value = ' value="' . escape($value) . '"';
}

if ($this->id) {
    $id = ' id="' . escape((string) $this->id) . '"';
}

$name = (string) $this->getVar('name', '');
$name = $name ? ' name="' . escape($name) . '"' : '';

echo '<search class="' . $class . '" data-size="xs"' . $id . '>
      <span class="rex-input-group-text"><i class="rex-icon rex-icon-search" aria-hidden="true"></i></span>
      <input class="rex-input" type="search"' . $name . $autofocus . $placeholder . $value . '>
</search>';
