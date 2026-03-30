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
 * - string|null                             $nonce        if set, renders the jQuery focus <script>
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
        <?php if (isset($this->nonce) && '' !== $this->nonce): ?>
            <script type="text/javascript" nonce="<?= $this->nonce ?>">
                <!--
                jQuery(function($) {
                    $(":input:visible:enabled:not([readonly]):first", $("#REX_FORM")).focus();
                });
                //-->
            </script>
        <?php endif ?>
    <?php endif ?>
</li>
