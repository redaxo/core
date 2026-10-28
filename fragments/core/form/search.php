<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$id = '';
$class = $this->getVar('class', 'rex-input-group');
$label = (string) $this->getVar('placeholder', I18n::msg('search_placeholder'));
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
    $id = ' id="' . $this->id . '"';
}

$name = (string) $this->getVar('name', '');
$name = $name ? ' name="' . $name . '"' : '';

echo '<search class="' . $class . '" data-size="xs"' . $id . '>
      <span class="rex-input-group-text"><i class="rex-icon rex-icon-search" aria-hidden="true"></i></span>
      <input class="rex-input" type="search"' . $name . $autofocus . $placeholder . $value . '>
</search>';
