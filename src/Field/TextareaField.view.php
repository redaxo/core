<?php

use Redaxo\Core\Field\FieldBinding;
use Redaxo\Core\Field\TextareaField;

use function Redaxo\Core\View\escape;

return static function (TextareaField $field, FieldBinding $binding): void { ?>
    <textarea <?= $field->textareaAttributes() ?>><?= escape((string) $binding->value) ?></textarea>
<?php };
