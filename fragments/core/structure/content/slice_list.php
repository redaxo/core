<?php

use Redaxo\Core\View\Fragment;

/**
 * Fragment variables (via $this->...):
 * - string $content  The rendered HTML of all slice <li> elements
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
if ('' === $this->content) {
    return;
}
?>
<ul class="rex-slices">
    <?= $this->content ?>
</ul>
