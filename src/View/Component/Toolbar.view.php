<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Toolbar;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

return static function (Toolbar $toolbar): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value;
    $target = 'rex-toolbar-' . bin2hex(random_bytes(4)) ?>
    <nav <?= new HtmlAttributes(['class' => ['rex-toolbar', $toolbar->cssClass => '' !== $toolbar->cssClass], 'data-variant' => $toolbar->inverse ? 'inverse' : null]) ?>>
        <?php if ($filled($toolbar->brand)): ?>
            <span class="rex-toolbar-brand"><?= Html::from($toolbar->brand) ?></span>
        <?php endif ?>
        <button type="button" class="rex-toolbar-toggle" data-toggle="collapse" data-target="#<?= $target ?>" aria-controls="<?= $target ?>" aria-expanded="false">
            <i class="rex-icon fa-solid fa-bars" aria-hidden="true"></i><span class="rex-u-visually-hidden"><?= I18n::msg('toolbar_toggle') ?></span>
        </button>
        <div class="rex-toolbar-content" id="<?= $target ?>">
            <?= Html::from($toolbar->content) ?>
        </div>
    </nav>
<?php };
