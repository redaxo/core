<?php

use Redaxo\Core\Http\Response;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

/**
 *  Fragment variables (via $this->...):
 *
 *  - array<int|string, int|string|list<string>>  $attributes   HTML attributes for the <li> element
 *  - string  $content  The rendered inner HTML of the slice
 *  - string|null  $formAction  if set, wraps content in a <form>
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<li<?= Str::buildAttributes($this->attributes ?? []) ?>>
    <?php if (isset($this->formAction) && '' !== $this->formAction): ?>
        <form enctype="multipart/form-data" action="<?= $this->formAction ?>" method="post" id="REX_FORM">
    <?php endif ?>

    <?= $this->content ?>

    <?php if (isset($this->formAction) && '' !== $this->formAction): ?>
        </form>
        <script nonce="<?= Response::getNonce() ?>">
            // focus the first field once the widgets have enhanced the form
            addEventListener('DOMContentLoaded', () => requestAnimationFrame(() => {
                [...document.getElementById('REX_FORM').querySelectorAll('input, select, textarea, button')]
                    .find(field => 'hidden' !== field.type && !field.disabled && !field.readOnly && field.checkVisibility())
                    ?.focus();
            }));
        </script>
    <?php endif ?>
</li>
