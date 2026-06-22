<?php

use Redaxo\Core\Field\CheckboxField;

return static function (CheckboxField $field): void { ?>
    <input <?= $field->checkboxAttributes() ?>>
<?php };
