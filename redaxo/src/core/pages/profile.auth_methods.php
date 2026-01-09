<?php

$currentAuth = false;
if (!isset($userId) || 1 > $userId) {
    $userId = rex::requireUser()->getId();
}

$list = rex_list::factory('
    select null as id, password_changed as createdate from ' . rex::getTable('user') . ' where id = ' . (int) $userId . ' AND password IS NOT NULL
    union
    select id, createdate from ' . rex::getTable('user_passkey') . ' where user_id = ' . (int) $userId . '
');
$list->addTableAttribute('class', 'table-hover');

$list->addColumn('remove_auth', '<i class="rex-icon rex-icon-delete"></i>', 0, ['<th class="rex-table-icon"></th>', '<td class="rex-table-icon">###VALUE###</td>']);
$list->setColumnParams('remove_auth', ['user_id' => $userId] + rex_api_user_remove_auth_method::getUrlParams());
$currentAuth = $userId == rex::requireUser()->getId() ? rex::getProperty('login')->getPasskey() : false;
$list->setColumnFormat('remove_auth', 'custom', static function () use ($list, $currentAuth) {
    $id = $list->getValue('id');

    // prevent removing the current auth method
    if ($currentAuth === $id) {
        return '';
    }

    if (null === $id) {
        $params = ['password' => 1];
    } else {
        $params = ['passkey_id' => $id];
    }

    return $list->getColumnLink('remove_auth', $list->getValue('remove_auth'), $params);
});
$list->addLinkAttribute('remove_auth', 'data-confirm', rex_i18n::msg('confirm_remove_auth'));

$list->setColumnLabel('id', rex_i18n::msg('auth_method'));
$list->setColumnLabel('createdate', rex_i18n::msg('created_on'));

$list->setColumnFormat('id', 'custom', static function () use ($list) {
    $id = $list->getValue('id');

    if (null === $id) {
        return '<span class="label label-default">' . rex_i18n::msg('password') . '</span>';
    }

    return '<span class="label label-default">' . rex_i18n::msg('passkey') . '</span> ' . rex_escape($id);
});
$list->setColumnFormat('createdate', 'custom', static function () use ($list) {
    return rex_formatter::intlDateTime((string) $list->getValue('createdate'), IntlDateFormatter::SHORT);
});

$content = $list->get();

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('auth_methods_caption'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
