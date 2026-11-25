<?php

use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;

return static function (PageHeader $header): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value ?>
    <header class="rex-pageheader">
        <div class="rex-pageheader-head">
            <h1 class="rex-pageheader-title">
                <?php if ($filled($header->eyebrow)): ?>
                    <span class="rex-pageheader-eyebrow"><?= Html::from($header->eyebrow) ?></span><span class="rex-pageheader-separator"><span class="rex-u-visually-hidden">: </span></span>
                <?php endif ?>
                <?= Html::from($header->heading) ?>
                <?php if ($filled($header->subheading)): ?>
                    <small><?= Html::from($header->subheading) ?></small>
                <?php endif ?>
            </h1>
            <?php if ($filled($header->meta)): ?>
                <p class="rex-pageheader-meta"><?= Html::from($header->meta) ?></p>
            <?php endif ?>
        </div>
        <?= $filled($header->navigation) ? Html::from($header->navigation) : '' ?>
    </header>
<?php };
