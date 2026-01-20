<?php
/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */
?>
    <footer class="rex-global-footer">
        <nav class="rex-nav-footer">
            <ul class="list-inline">
                <li><a href="#rex-start-of-page"><i class="fa fa-arrow-up"></i></a></li>
                <li><a href="https://www.yakamara.de" target="_blank" rel="noreferrer noopener">yakamara.de</a></li>
                <li><a href="https://www.redaxo.org" target="_blank" rel="noreferrer noopener">redaxo.org</a></li>
                <?php if (rex::getUser()?->isAdmin()): ?>
                    <li><a href="https://www.redaxo.org/doku/5.x" target="_blank" rel="noreferrer noopener"><?= rex_i18n::msg('footer_doku') ?></a></li>
                <?php endif ?>
                <li><a href="<?= rex::getUser() ? rex_url::backendPage('credits') : 'https://www.redaxo.org/" target="_blank" rel="noreferrer noopener' ?>"><?= rex_i18n::msg('footer_credits') ?></a></li>
                <li class="rex-js-script-time"><!--DYN--><?= rex_i18n::msg('footer_scripttime', $this->time) ?><!--/DYN--></li>
            </ul>
        </nav>
    </footer>
