<?php

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$error = '';
$success = '';
$message = '';
$logFile = Path::log('cronjob.log');

if ('cronjob_delLog' == $func) {
    if (LogFile::delete($logFile)) {
        $success = I18n::msg('syslog_deleted');
    } else {
        $error = I18n::msg('syslog_delete_error');
    }
}
if ('' != $success) {
    $message .= Message::success($success);
}
if ('' != $error) {
    $message .= Message::error($error);
}
$content = '';

$content .= '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon"></th>
                        <th>' . I18n::msg('cronjob_log_date') . '</th>
                        <th>' . I18n::msg('cronjob_name') . '</th>
                        <th>' . I18n::msg('cronjob_log_message') . '</th>
                        <th>' . I18n::msg('cronjob_environment') . '</th>
                    </tr>
                </thead>
                <tbody>';

$formElements = [];

$file = LogFile::factory($logFile);

/** @var LogEntry $entry */
foreach (new LimitIterator($file, 0, 100) as $entry) {
    $data = $entry->getData();
    $class = 'ERROR' == trim($data[0]) ? 'rex-state-error' : 'rex-state-success';
    if ('--' == $data[1]) {
        $icon = '<i class="rex-icon rex-icon-cronjob" title="' . I18n::msg('cronjob_not_editable') . '"></i>';
    } else {
        $icon = '<a href="' . Url::backendPage('cronjob', ['list' => 'cronjobs', 'func' => 'edit', 'oid' => $data[1]]) . '" title="' . I18n::msg('cronjob_edit') . '"><i class="rex-icon rex-icon-cronjob"></i></a>';
    }
    $content .= '
        <tr class="' . $class . '">
            <td class="rex-table-icon">' . $icon . '</td>
            <td data-title="' . I18n::msg('cronjob_log_date') . '" class="rex-table-tabular-nums">' . Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</td>
            <td data-title="' . I18n::msg('cronjob_name') . '">' . escape($data[2]) . '</td>
            <td data-title="' . I18n::msg('cronjob_log_message') . '">' . nl2br(escape($data[3])) . '</td>
            <td data-title="' . I18n::msg('cronjob_environment') . '">' . (isset($data[4]) ? I18n::msg('cronjob_environment_' . $data[4]) : '') . '</td>
        </tr>';
}

// XXX calc last line and use it instead
if ($url = Editor::factory()->getUrl($logFile, 1)) {
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '">' . I18n::msg('system_editor_open_file', Path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . I18n::msg('cronjob_delete_log_msg') . '?">' . I18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '
                </tbody>
            </table>';

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        <input type="hidden" name="func" value="cronjob_delLog" />
        ' . $content . '
    </form>';

echo $message;
echo $content;
