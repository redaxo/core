<?php

use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Backend\Layout\TopBar;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (TopBar $bar): void {
    // decorative, the link carries the name as text
    $logo = Html::raw(str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', (string) File::get(Path::coreAssets('redaxo-logo.svg'))));
    $instanceColor = Appearance::getInstanceColor() ?>
    <header <?= new HtmlAttributes([
        'id' => 'rex-js-nav-top',
        'class' => ['rex-topbar', 'rex-topbar--fixed' => !$bar->popup, 'rex-topbar--popup' => $bar->popup, 'rex-topbar--colored' => null !== $instanceColor],
    ]) ?>>
        <?php if (null !== $instanceColor): ?>
            <style nonce="<?= Response::getNonce() ?>">.rex-topbar { --rex-instance-color: <?= escape($instanceColor) ?>; }</style>
        <?php endif ?>
        <?php if (!$bar->login && !$bar->popup): ?>
            <button type="button" class="rex-topbar__toggle" id="rex-js-nav-main-toggle" aria-controls="rex-js-nav-main" aria-expanded="false">
                <span class="rex-u-visually-hidden"><?= I18n::msg('navigation_toggle') ?></span>
                <span class="rex-topbar__bars"><span></span><span></span><span></span></span>
            </button>
        <?php endif ?>
        <div class="rex-topbar__head">
            <?php if ($bar->popup): ?>
                <span class="rex-topbar__brand"><?= $logo ?></span>
            <?php else: ?>
                <a class="rex-topbar__brand" href="<?= Url::backendController() ?>"><?= $logo ?><span class="rex-u-visually-hidden">REDAXO</span></a>
            <?php endif ?>
            <?php if (!$bar->popup && Core::getUser()?->admin && Core::isDevMode()): ?>
                <?php if (null !== $devModeMarkerUrl = Appearance::$devModeMarkerUrl): ?>
                    <a class="rex-topbar__devmode" href="<?= escape($devModeMarkerUrl) ?>" target="_blank" title="<?= I18n::msg('dev_mode_marker') ?>">
                        <i class="rex-icon rex-icon-heartbeat rex-u-pulse" aria-hidden="true"></i>
                        <span class="rex-u-visually-hidden"><?= I18n::msg('dev_mode_marker') ?> (<?= I18n::msg('link_new_window') ?>)</span>
                    </a>
                <?php else: ?>
                    <span class="rex-topbar__devmode" title="<?= I18n::msg('dev_mode_marker') ?>">
                        <i class="rex-icon rex-icon-heartbeat rex-u-pulse" aria-hidden="true"></i>
                        <span class="rex-u-visually-hidden"><?= I18n::msg('dev_mode_marker') ?></span>
                    </span>
                <?php endif ?>
            <?php endif ?>
            <?php if (!$bar->popup && Core::getUser()): ?>
                <p class="rex-topbar__title">
                    <a href="<?= Url::frontend() ?>" target="_blank" rel="noreferrer noopener"><span class="rex-topbar__title-name"><?= escape(Core::getProject()->instanceName) ?></span><i class="rex-icon rex-icon-external-link" aria-hidden="true"></i><span class="rex-u-visually-hidden"> (<?= I18n::msg('link_new_window') ?>)</span></a>
                </p>
            <?php endif ?>
        </div>
        <?php if ($bar->metaItems): ?>
            <ul class="rex-topbar__meta">
                <?php foreach ($bar->metaItems as $item): ?>
                    <?= $item ?>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </header>
<?php };
