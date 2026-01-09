<?php
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
    <footer class="rex-global-footer">
        <nav class="rex-nav-footer">
            <ul class="list-inline">
                <li><a href="#rex-start-of-page"><i class="fa fa-arrow-up"></i></a></li>
                <li><a href="https://www.yakamara.de" target="_blank" rel="noreferrer noopener">yakamara.de</a></li>
                <li><a href="https://www.redaxo.org" target="_blank" rel="noreferrer noopener">redaxo.org</a></li>
                <?php if (Core::getUser()?->isAdmin()): ?>
                    <li><a href="https://www.redaxo.org/doku/main" target="_blank" rel="noreferrer noopener"><?= I18n::msg('footer_doku') ?></a></li>
                <?php endif ?>
                <li><a href="<?= Core::getUser() ? Url::backendPage('credits') : 'https://www.redaxo.org/" target="_blank" rel="noreferrer noopener' ?>"><?= I18n::msg('footer_credits') ?></a></li>
                <li class="rex-js-script-time"><!--DYN--><?= I18n::msg('footer_scripttime', $this->time) ?><!--/DYN--></li>
            </ul>
        </nav>
    </footer>
