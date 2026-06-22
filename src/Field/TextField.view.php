<?php

use Redaxo\Core\Field\TextField;

return static function (TextField $field): void { ?>
    <input <?= $field->inputAttributes() ?>>
<?php };
