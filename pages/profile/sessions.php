<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\ApiFunction\UserRemoveSession;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Badge;
use Redaxo\Core\View\Component\BadgeVariant;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

if (!isset($userId) || 1 > $userId) {
    $userId = Core::requireUser()->id;
}
Type::int($userId);

$list = new DataList('
    SELECT session_id, cookie_key, ip, useragent, starttime, last_activity
    FROM rex_user_session
    WHERE user_id = ' . $userId . '
    ORDER BY session_id = ' . Sql::factory()->escape((string) session_id()) . ' DESC, last_activity DESC
');
$list->attributes = new HtmlAttributes(['data-hover' => true]);

$isCurrent = static fn (array $row): bool => $row['session_id'] === session_id();
// the session id itself must not be exposed, a short hash is enough to tell the sessions apart
$hash = static fn (array $row): string => UserSession::hashSessionId((string) $row['session_id']);

$list->column(
    'remove_session',
    '',
    static fn (array $row): ?Html => $isCurrent($row) ? null : Html::raw('<i class="rex-icon rex-icon-delete"></i>'),
    type: ColumnType::Icon,
    // the current session can not be removed
    link: static fn (array $row): ?string => $isCurrent($row) ? null : $list->getUrl(['user_id' => $userId, 'session_hash' => $hash($row)] + UserRemoveSession::getUrlParams()),
    linkAttributes: ['data-confirm' => I18n::msg('confirm_remove_session')],
);
$list->column('session_id', I18n::msg('session_id'), static fn (array $row): Html => Html::raw(
    escape(substr($hash($row), 0, 12)) . ($row['cookie_key'] ? ' ' . (string) new Badge(I18n::msg('stay_logged_in'), BadgeVariant::Warning)->render() : ''),
));
$list->column('ip', I18n::msg('ip'));
$list->column('useragent', I18n::msg('user_agent'));
$list->column('starttime', I18n::msg('starttime'), static fn (array $row): string => Formatter::intlDateTime((string) $row['starttime'], IntlDateFormatter::SHORT));
$list->column('last_activity', I18n::msg('last_activity'), static fn (array $row): string|Badge => $isCurrent($row)
    ? new Badge(I18n::msg('active_session'), BadgeVariant::Info)
    : Formatter::intlDateTime((string) $row['last_activity'], IntlDateFormatter::SHORT));
$content = (string) $list->render();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('session_caption'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
