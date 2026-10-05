<?php

if (!isset($userId) || 1 > $userId) {
    $userId = rex::requireUser()->getId();
}

$list = rex_list::factory('
    SELECT session_id, cookie_key, ip, useragent, starttime, last_activity
    FROM ' . rex::getTable('user_session') . '
    WHERE user_id = ' . (int) $userId . '
    ORDER BY session_id = ' . rex_sql::factory()->escape((string) session_id()) . ' DESC, last_activity DESC
');
$list->addTableAttribute('class', 'table-hover');

$list->addColumn('remove_session', '<i class="rex-icon rex-icon-delete"></i>', 0, ['<th class="rex-table-icon"></th>', '<td class="rex-table-icon">###VALUE###</td>']);
$list->setColumnParams('remove_session', ['session_id' => '###session_id###', 'user_id' => $userId] + rex_api_user_remove_session::getUrlParams());
$list->setColumnFormat('remove_session', 'custom', static function () use ($list) {
    // prevent removing the current session
    if ($list->getValue('session_id') === session_id()) {
        return '';
    }
    return $list->getColumnLink('remove_session', $list->getValue('remove_session'));
});
$list->addLinkAttribute('remove_session', 'data-confirm', rex_i18n::msg('confirm_remove_session'));

$list->removeColumn('cookie_key');
$list->setColumnLabel('session_id', rex_i18n::msg('session_id'));
$list->setColumnLabel('ip', rex_i18n::msg('ip'));
$list->setColumnLabel('useragent', rex_i18n::msg('user_agent'));
$list->setColumnLabel('starttime', rex_i18n::msg('starttime'));
$list->setColumnLabel('last_activity', rex_i18n::msg('last_activity'));

$list->setColumnFormat('session_id', 'custom', static function () use ($list) {
    return rex_escape((string) $list->getValue('session_id'))
        . ($list->getValue('cookie_key') ? ' <span class="label label-warning">' . rex_i18n::msg('stay_logged_in') . '</span>' : '');
});
$list->setColumnFormat('last_activity', 'custom', static function () use ($list) {
    if (session_id() === $list->getValue('session_id')) {
        return '<span class="label label-info">' . rex_i18n::msg('active_session') . '</span>';
    }
    return rex_formatter::intlDateTime((string) $list->getValue('last_activity'), IntlDateFormatter::SHORT);
});
$list->setColumnFormat('starttime', 'custom', static function () use ($list) {
    return rex_formatter::intlDateTime((string) $list->getValue('starttime'), IntlDateFormatter::SHORT);
});
$content = $list->get();

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('session_caption'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
