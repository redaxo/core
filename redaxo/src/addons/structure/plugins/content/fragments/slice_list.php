<?php
/**
 * Fragment variables (via $this->...):
 * - string $content  The rendered HTML of all slice <li> elements
 *
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */
if ('' === $this->content) {
    return;
}
?>
<ul class="rex-slices">
    <?= $this->content ?>
</ul>
