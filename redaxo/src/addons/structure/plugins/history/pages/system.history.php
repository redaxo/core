<?php

$plugin = rex_plugin::get('structure', 'history');

$csrfToken = rex_csrf_token::factory('structure_history');

if ('clearall' == rex_request('func', 'string')) {
    if (!$csrfToken->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        rex_article_slice_history::clearAllHistory();
        echo rex_view::success($plugin->i18n('deleted'));
    }
}

$content = rex_i18n::rawMsg('structure_history_info_content');
$content .= '<p><a href="' . rex_url::currentBackendPage(['func' => 'clearall'] + $csrfToken->getUrlParams()) . '" class="btn btn-setup" data-confirm="' . rex_i18n::msg('delete') . ' ?">' . $plugin->i18n('button_delete_history') . '</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('title', $plugin->i18n('title_info'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
