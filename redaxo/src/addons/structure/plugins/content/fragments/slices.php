<?php
/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 *
 * @var string $content  The rendered HTML of all slice <li> elements
 */
if ('' === $this->content) {
    return;
}
?>
<ul class="rex-slices">
    <?= $this->content ?>
</ul>

