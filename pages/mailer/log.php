<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$error = '';
$success = '';
$logFile = Mailer::logFile();

if ('mailer_delLog' == $func) {
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

$content = '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>' . I18n::msg('phpmailer_log_success') . '</th>
                        <th>' . I18n::msg('phpmailer_log_date') . '</th>
                        <th>' . I18n::msg('phpmailer_log_from') . '</th>
                        <th>' . I18n::msg('phpmailer_log_to') . '</th>
                        <th>' . I18n::msg('phpmailer_log_subject') . '</th>
                        <th>' . I18n::msg('phpmailer_log_msg') . '</th>
                    </tr>
                </thead>
                <tbody>';

$file = LogFile::factory($logFile);
foreach (new LimitIterator($file, 0, 30) as $entry) {
    $data = $entry->getData();
    $class = 'ERROR' == trim($data[0]) ? 'rex-state-error' : 'rex-mailer-log-ok';
    $content .= '
                <tr class="' . $class . '">
                  <td data-title="' . I18n::msg('phpmailer_log_success') . '"><strong>' . escape($data[0]) . '</strong></td>
                  <td data-title="' . I18n::msg('phpmailer_log_date') . '" class="rex-table-tabular-nums">' . Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</td>
                  <td data-title="' . I18n::msg('phpmailer_log_from') . '">' . escape($data[1]) . '</td>
                  <td data-title="' . I18n::msg('phpmailer_log_to') . '">' . escape($data[2]) . '</td>
                  <td data-title="' . I18n::msg('phpmailer_log_subject') . '">' . escape($data[3]) . '</td>
                  <td data-title="' . I18n::msg('phpmailer_log_msg') . '">' . nl2br(escape($data[4])) . '</td>
                </tr>';
}

$content .= '
                </tbody>
            </table>';

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . I18n::msg('phpmailer_delete_log_msg') . '">' . I18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('phpmailer_log_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');
$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        <input type="hidden" name="func" value="mailer_delLog" />
        ' . $content . '
    </form>';
echo $message;
echo $content;
