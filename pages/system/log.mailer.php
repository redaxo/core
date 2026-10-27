<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$error = '';
$success = '';
$logFile = Mailer::logFile();

$csrfToken = CsrfToken::factory('mailer-delete-log');

if ('mailer_delLog' == $func && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('mailer_delLog' == $func) {
    if (LogFile::delete($logFile)) {
        $success = I18n::msg('syslog_deleted');
    } else {
        $error = I18n::msg('syslog_delete_error');
    }
}
$message = '';
if ('' != $success) {
    $message .= Message::success($success);
}
if ('' != $error) {
    $message .= Message::error($error);
}

$rows = [];
$file = LogFile::factory($logFile);
/** @var LogEntry $entry */
foreach (new LimitIterator($file, 0, 30) as $entry) {
    $data = $entry->getData();
    $rows[] = [
        'error' => 'ERROR' == trim(Type::string($data[0])),
        'state' => Html::raw('<strong>' . escape(Type::string($data[0])) . '</strong>'),
        'date' => Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]),
        'from' => Type::string($data[1]),
        'to' => Type::string($data[2]),
        'subject' => Type::string($data[3]),
        'message' => Html::raw(nl2br(escape(Type::string($data[4])))),
    ];
}

$content = (string) new Table([
    TableColumn::forKey(I18n::msg('mailer_log_success'), 'state'),
    TableColumn::forKey(I18n::msg('mailer_log_date'), 'date', attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
    TableColumn::forKey(I18n::msg('mailer_log_from'), 'from'),
    TableColumn::forKey(I18n::msg('mailer_log_to'), 'to'),
    TableColumn::forKey(I18n::msg('mailer_log_subject'), 'subject'),
    TableColumn::forKey(I18n::msg('mailer_log_msg'), 'message'),
], $rows, rowAttributes: static fn (array $row): HtmlAttributes => new HtmlAttributes([
    'class' => $row['error'] ? 'rex-state-error' : 'rex-mailer-log-ok',
]), attributes: new HtmlAttributes(['class' => ['table-hover']]))->render();

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . I18n::msg('mailer_delete_log_msg') . '">' . I18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('mailer_log_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');
$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        <input type="hidden" name="func" value="mailer_delLog" />
        ' . $content . '
    </form>';
echo $message;
echo $content;
