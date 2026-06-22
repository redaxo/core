<?php

use Redaxo\Core\Field\DateField;

return static function (DateField $field): void { ?>
    <input <?= $field->inputAttributes() ?>>
<?php };
