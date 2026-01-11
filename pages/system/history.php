<?php

use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

if ('clearall' == Request::request('func', 'string')) {
    ArticleSliceHistory::clearAllHistory();
    echo Message::success(I18n::msg('structure_history_deleted'));
}

$content = I18n::rawMsg('structure_history_info_content');
$content .= '<p><a href="' . Url::currentBackendPage(['func' => 'clearall']) . '" class="btn btn-setup">' . I18n::msg('structure_history_button_delete_history') . '</a></p>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('structure_history_title_info'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('structure_history_todos'));
$fragment->setVar('body', I18n::rawMsg('structure_history_todos_content'), false);
echo $fragment->parse('core/page/section.php');
