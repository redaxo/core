<?php

use Redaxo\Core\Core;
use Redaxo\Core\Security\ApiFunction\UserRemoveSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

if (!isset($userId) || 1 > $userId) {
    $userId = Core::requireUser()->getId();
}

$list = DataList::factory('Select session_id, cookie_key, ip, useragent, starttime, last_activity from ' . Core::getTablePrefix() . 'user_session where user_id = ' . (int) $userId . ' ORDER BY last_activity DESC');
$list->addTableAttribute('class', 'table-hover');

$list->addColumn('remove_session', '<i class="rex-icon rex-icon-delete"></i>', 0, ['<th class="rex-table-icon"></th>', '<td class="rex-table-icon">###VALUE###</td>']);
$list->setColumnParams('remove_session', ['session_id' => '###session_id###', 'user_id' => $userId] + UserRemoveSession::getUrlParams());
$list->setColumnFormat('remove_session', 'custom', static function () use ($list) {
    // prevent removing the current session
    if ($list->getValue('session_id') === session_id()) {
        return '';
    }
    return $list->getColumnLink('remove_session', $list->getValue('remove_session'));
});
$list->addLinkAttribute('remove_session', 'data-confirm', I18n::msg('confirm_remove_session'));

$list->removeColumn('cookie_key');
$list->setColumnLabel('session_id', I18n::msg('session_id'));
$list->setColumnLabel('ip', I18n::msg('ip'));
$list->setColumnLabel('useragent', I18n::msg('user_agent'));
$list->setColumnLabel('starttime', I18n::msg('starttime'));
$list->setColumnLabel('last_activity', I18n::msg('last_activity'));

$list->setColumnFormat('session_id', 'custom', static function () use ($list) {
    return escape((string) $list->getValue('session_id'))
        . ($list->getValue('cookie_key') ? ' <span class="label label-warning">' . I18n::msg('stay_logged_in') . '</span>' : '');
});
$list->setColumnFormat('last_activity', 'custom', static function () use ($list) {
    if (session_id() === $list->getValue('session_id')) {
        return '<span class="label label-info">' . I18n::msg('active_session') . '</span>';
    }
    return Formatter::intlDateTime((string) $list->getValue('last_activity'), IntlDateFormatter::SHORT);
});
$list->setColumnFormat('starttime', 'custom', static function () use ($list) {
    return Formatter::intlDateTime((string) $list->getValue('starttime'), IntlDateFormatter::SHORT);
});
$content = $list->get();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('session_caption'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
