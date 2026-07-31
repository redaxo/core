<?php
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$isPopup = Controller::requireCurrentPageObject()->isPopup();
$isLogin = ('login' === Controller::getCurrentPage());
$isSetup = ('setup' === Controller::getCurrentPage());

$instanceColor = Core::getInstanceColor();
?>

        <div id="rex-js-nav-top" class="rex-nav-top<?php if (!$isPopup && !$isSetup): ?> rex-nav-top-is-fixed<?php endif ?>">
            <nav class="navbar navbar-default"<?= null !== $instanceColor ? ' style="border-bottom: 5px solid ' . escape($instanceColor) . '"' : '' ?>>
                <div class="container-fluid">

                    <?php if (!$isLogin && !$isPopup): ?>
                        <button type="button" class="navbar-toggle" id="rex-js-nav-main-toggle">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="icon-bars">
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                            </span>
                        </button>
                    <?php endif ?>

                    <div class="navbar-header">
                        <?php if ($isPopup): ?>
                            <span class="navbar-brand"><?= File::get(Path::coreAssets('redaxo-logo.svg')) ?></span>
                        <?php else: ?>
                            <a class="navbar-brand" href="<?= Url::backendController() ?>"><?= File::get(Path::coreAssets('redaxo-logo.svg')) ?></a>
                        <?php endif ?>
                        <?php if (!$isPopup && Core::getUser()?->admin && Core::isDevMode()): ?>
                            <a class="rex-marker-devmode" href="<?= Url::backendPage('system/settings') ?>" title="<?= I18n::msg('dev_mode_marker') ?>">
                                <i class="rex-icon rex-icon-heartbeat rex-pulse"></i>
                            </a>
                        <?php endif ?>
                        <?php if (!$isPopup && Core::getUser()): ?>
                            <h1 class="rex-nav-top-title"><a href="<?= Url::frontend() ?>" target="_blank" rel="noreferrer noopener"><span class="rex-nav-top-title-name"><?= escape(Core::getServerName()) ?></span><i class="rex-icon rex-icon-external-link"></i></a></h1>
                        <?php endif ?>
                    </div>

                    <?= $this->meta_navigation ?>

                </div>
            </nav>

        </div>
