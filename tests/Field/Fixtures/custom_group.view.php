<?php

use Redaxo\Core\Field\FieldGroup;

return static function (FieldGroup $group): void { ?>
    <div class="my-group"><?= $group->field->renderInput() ?></div>
<?php };
