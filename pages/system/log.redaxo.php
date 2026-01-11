<?php

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$error = '';
$success = '';

$func = Request::request('func', 'string');
$logFile = Logger::getPath();

$csrfToken = CsrfToken::factory('system');

if ($func && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('delLog' == $func) {
    // close logger, to free remaining file-handles to syslog
    // so we can safely delete the file
    Logger::close();

    if (LogFile::delete($logFile)) {
        $success = I18n::msg('syslog_deleted');
    } else {
        $error = I18n::msg('syslog_delete_error');
    }
} elseif ('download' == $func && is_file($logFile)) {
    Response::sendFile($logFile, 'application/octet-stream', 'attachment');
    exit;
}

if ('' != $success) {
    echo Message::success($success);
}

if ('' != $error) {
    echo Message::error($error);
}

$content = '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>' . I18n::msg('syslog_timestamp') . '</th>
                        <th>' . I18n::msg('syslog_message') . '</th>
                    </tr>
                </thead>
                <tbody>';

$editor = Editor::factory();

$file = LogFile::factory($logFile);
foreach (new LimitIterator($file, 0, 100) as $entry) {
    /** @var LogEntry $entry */
    $data = $entry->getData();

    $type = Type::string($data[0]);
    $message = Type::string($data[1]);
    $file = $data[2] ?? null;
    $line = $data[3] ?? null;

    $class = match (strtolower($type)) {
        'success' => 'success',
        'debug' => 'default',
        'info', 'notice', 'deprecated' => 'info',
        'warning' => 'warning',
        default => 'danger',
    };

    $path = '';
    if ($file) {
        $path = escape($file . (null === $line ? '' : ':' . $line));

        $fullPath = str_starts_with($file, 'rex://') ? $file : Path::base($file);
        if ($url = $editor->getUrl($fullPath, (int) ($line ?? 1))) {
            $path = '<a href="' . $url . '">' . $path . '</a>';
        }
        $path = '<small class="rex-word-break"><span class="label label-default">' . I18n::msg('syslog_file') . ':</span> ' . $path . '</small><br>';
    }

    $url = $data[4] ?? null;
    if ($url) {
        $url = escape($url);
        $url = '<small class="rex-word-break"><span class="label label-default">' . I18n::msg('syslog_url') . ':</span> <a href="' . $url . '">' . $url . '</a></small>';
    } else {
        $url = '';
    }

    $content .= '
                <tr>
                    <td data-title="' . I18n::msg('syslog_timestamp') . '" class="rex-table-tabular-nums rex-table-date">
                        <small>' . Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</small><br>
                        <span class="label label-' . $class . '">' . escape($type) . '</span>
                    </td>
                    <td data-title="' . I18n::msg('syslog_message') . '">
                        <div class="rex-word-break"><b style="font-weight: 500">' . nl2br(escape($message)) . '</b></div>
                        ' . $path . '
                        ' . $url . '
                    </td>
                </tr>';
}

$content .= '
                </tbody>
            </table>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . I18n::msg('delete') . '?">' . I18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

if ($url = $editor->getUrl($logFile, 0)) {
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '">' . I18n::msg('system_editor_open_file', Path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

if (is_file($logFile)) {
    $url = Url::currentBackendPage(['func' => 'download'] + $csrfToken->getUrlParams());
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '" download>' . I18n::msg('syslog_download', Path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('syslog_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        <input type="hidden" name="func" value="delLog" />
        ' . $csrfToken->getHiddenField() . '
        ' . $content . '
    </form>';

echo $content;
