<?php

use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;

return static function (PageHeader $header): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value ?>
    <header class="rex-pageheader">
        <h1 class="rex-pageheader-title">
            <?= Html::from($header->heading) ?>
            <?php if ($filled($header->subheading)): ?>
                <small><?= Html::from($header->subheading) ?></small>
            <?php endif ?>
        </h1>
        <?= $filled($header->navigation) ? Html::from($header->navigation) : '' ?>
    </header>
<?php };
