<?php

use Redaxo\Core\Field\DateTimeField;

return static function (DateTimeField $field): void { ?>
    <input <?= $field->inputAttributes() ?>>
<?php };
