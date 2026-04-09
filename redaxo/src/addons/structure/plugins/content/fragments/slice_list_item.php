<?php
/**
 *  Fragment variables (via $this->...):
 *
 *  - array<int|string, int|string|list<string>>  $attributes   HTML attributes for the <li> element
 *  - string  $content  The rendered inner HTML of the slice
 *  - string|null  $formAction  if set, wraps content in a <form>
 *
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */
?>
<li<?= rex_string::buildAttributes($this->attributes ?? []) ?>>
    <?php if (isset($this->formAction) && '' !== $this->formAction): ?>
        <form enctype="multipart/form-data" action="<?= $this->formAction ?>" method="post" id="REX_FORM">
    <?php endif ?>

    <?= $this->content ?>

    <?php if (isset($this->formAction) && '' !== $this->formAction): ?>
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
