<?php

use Redaxo\Core\Field\ChoiceField;

return static function (ChoiceField $field): void {
    if ($field->expanded) {
        echo $field->renderExpandedItems();

        return;
    }
    ?>
    <select <?= $field->selectAttributes() ?>><?= $field->renderOptions() ?></select>
<?php };
