<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

$id = '';
$class = $this->getVar('class', 'input-group input-group-xs has-feedback form-clear-button');
$clear = rex_i18n::msg('search_clear');
$placeholder = ' placeholder="' . $this->getVar('placeholder', rex_i18n::msg('search_placeholder')) . '"';
$autofocus = '';
if (isset($this->autofocus) && $this->autofocus) {
    $autofocus = ' autofocus ';
}
$value = (string) $this->getVar('value', '');
if ('' !== $value) {
    $value = ' value="' . rex_escape($value) . '"';
}

if ($this->id) {
    $id = ' id="' . $this->id . '"';
}

$name = $this->name ?? '';
$name = $name ? ' name="' . $name . '"' : '';

echo '<search role="search" class="' . $class . '"' . $id . '>
      <span class="input-group-addon clear-button"><i class="rex-icon rex-icon-search"></i></span>
      <input class="form-control" type="text"' . $name . $autofocus . $placeholder . $value . '>
      <span title="' . $clear . '" class="form-control-clear rex-icon rex-icon-clear form-control-feedback hidden"></span>
</search>';
