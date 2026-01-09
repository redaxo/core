<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$id = '';
$class = $this->getVar('class', 'input-group input-group-xs has-feedback form-clear-button');
$clear = I18n::msg('search_clear');
$placeholder = ' placeholder="' . $this->getVar('placeholder', I18n::msg('search_placeholder')) . '"';
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

echo '<search role="search" class="' . $class . '"' . $id . '>
      <span class="input-group-addon clear-button"><i class="rex-icon rex-icon-search"></i></span>
      <input class="form-control" type="text"' . $autofocus . $placeholder . $value . '>
      <span title="' . $clear . '" class="form-control-clear rex-icon rex-icon-clear form-control-feedback hidden"></span>
</search>';
