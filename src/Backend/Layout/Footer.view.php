<?php

use Redaxo\Core\Backend\Layout\Footer;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;

return static function (Footer $footer): void { ?>
    <footer class="rex-footer">
        <ul class="rex-footer__list">
            <li><a href="#rex-start-of-page" aria-label="<?= I18n::msg('footer_top') ?>"><i class="fa fa-arrow-up"></i></a></li>
            <li><a href="https://www.yakamara.de" target="_blank" rel="noreferrer noopener">yakamara.de</a></li>
            <li><a href="https://www.redaxo.org" target="_blank" rel="noreferrer noopener">redaxo.org</a></li>
            <?php if (Core::getUser()?->admin): ?>
                <li><a href="https://www.redaxo.org/doku/5.x" target="_blank" rel="noreferrer noopener"><?= I18n::msg('footer_doku') ?></a></li>
            <?php endif ?>
            <?php if (Core::getUser()): ?>
                <li><a href="<?= Url::backendPage('credits') ?>"><?= I18n::msg('footer_credits') ?></a></li>
            <?php else: ?>
                <li><a href="https://www.redaxo.org/" target="_blank" rel="noreferrer noopener"><?= I18n::msg('footer_credits') ?></a></li>
            <?php endif ?>
            <li class="rex-js-script-time"><!--DYN--><?= I18n::msg('footer_scripttime', $footer->time) ?><!--/DYN--></li>
        </ul>
    </footer>
<?php };
