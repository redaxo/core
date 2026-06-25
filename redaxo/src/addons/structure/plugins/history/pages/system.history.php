<?php

$plugin = rex_plugin::get('structure', 'history');

if ('clearall' == rex_request('func', 'string')) {
    rex_article_slice_history::clearAllHistory();
    echo rex_view::success($plugin->i18n('deleted'));
}

$content = rex_i18n::rawMsg('structure_history_info_content');
$content .= '<p><a href="' . rex_url::currentBackendPage(['func' => 'clearall']) . '" class="btn btn-setup">' . $plugin->i18n('button_delete_history') . '</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('title', $plugin->i18n('title_info'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
