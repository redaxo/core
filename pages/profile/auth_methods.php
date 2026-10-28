<?php

use Redaxo\Core\Core;
use Redaxo\Core\Security\ApiFunction\UserRemoveAuthMethod;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Badge;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

$currentAuth = false;
if (!isset($userId) || 1 > $userId) {
    $userId = Core::requireUser()->id;
}
Type::int($userId);

$list = new DataList('
    select null as id, password_changed as createdate from rex_user where id = ' . $userId . ' AND password IS NOT NULL
    union
    select id, createdate from rex_user_passkey where user_id = ' . $userId . '
');
$list->attributes = new HtmlAttributes(['data-hover' => true]);

$currentAuth = $userId == Core::requireUser()->id ? BackendLogin::requireCurrent()->getPasskey() : false;
// the current auth method can not be removed
$removable = static fn (array $row): bool => $currentAuth !== $row['id'];

$list->column(
    'remove_auth',
    '',
    static fn (array $row): ?Html => $removable($row) ? Html::raw('<i class="rex-icon rex-icon-delete" aria-hidden="true"></i>') : null,
    type: ColumnType::Icon,
    link: static fn (array $row): ?string => $removable($row)
        ? $list->getUrl(['user_id' => $userId] + (null === $row['id'] ? ['password' => 1] : ['passkey_id' => (string) $row['id']]) + UserRemoveAuthMethod::getUrlParams())
        : null,
    linkAttributes: ['data-confirm' => I18n::msg('confirm_remove_auth')],
);
$list->column('id', I18n::msg('auth_method'), static fn (array $row): Html => null === $row['id']
    ? new Badge(I18n::msg('password'))->render()
    : Html::raw((string) new Badge(I18n::msg('passkey'))->render() . ' ' . escape((string) $row['id'])));
$list->column('createdate', I18n::msg('created_on'), static fn (array $row): string => Formatter::intlDateTime((string) $row['createdate'], IntlDateFormatter::SHORT));

$content = (string) $list->render();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('auth_methods_caption'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
