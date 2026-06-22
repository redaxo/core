<?php

use Redaxo\Core\Field\TimeField;

return static function (TimeField $field): void { ?>
    <input <?= $field->inputAttributes() ?>>
<?php };
