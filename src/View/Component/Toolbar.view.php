<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Toolbar;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

return static function (Toolbar $toolbar): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value;
    $target = 'rex-js-collapse-' . bin2hex(random_bytes(4)) ?>
    <nav <?= new HtmlAttributes(['class' => ['navbar', $toolbar->inverse ? 'navbar-inverse' : 'navbar-default', 'rex-navbar-without-brand' => !$filled($toolbar->brand), $toolbar->cssClass => '' !== $toolbar->cssClass]]) ?>>
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#<?= $target ?>" aria-controls="<?= $target ?>" aria-expanded="false">
                    <span class="sr-only"><?= I18n::msg('toolbar_toggle') ?></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <?php if ($filled($toolbar->brand)): ?>
                    <span class="navbar-brand"><?= Html::from($toolbar->brand) ?></span>
                <?php endif ?>
            </div>
            <div class="collapse navbar-collapse" id="<?= $target ?>">
                <?= Html::from($toolbar->content) ?>
            </div>
        </div>
    </nav>
<?php };
