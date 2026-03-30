<?php
/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 *
 * Fragment variables (via $this->...):
 *
 * - array{class: list<string>, id?: string} $attributes   HTML attributes for the <li> element
 * - string                                  $content      The rendered inner HTML of the slice
 * - string|null                             $form_action  if set, wraps content in a <form>
 */

$attributes = $this->attributes ?? [];
?>
<li<?= rex_string::buildAttributes($attributes) ?>>
    <?php if (isset($this->form_action) && '' !== $this->form_action): ?>
        <form enctype="multipart/form-data" action="<?= $this->form_action ?>" method="post" id="REX_FORM">
    <?php endif ?>

    <?= $this->content ?>

    <?php if (isset($this->form_action) && '' !== $this->form_action): ?>
        </form>
        <script type="text/javascript" nonce="<?= rex_response::getNonce() ?>">
            <!--
            jQuery(function($) {
                $(":input:visible:enabled:not([readonly]):first", $("#REX_FORM")).focus();
            });
            //-->
        </script>
    <?php endif ?>
</li>
