<?php

$error = '';
$success = '';

$func = rex_request('func', 'string');
$logFile = rex_logger::getPath();

$csrfToken = rex_csrf_token::factory('system');

if ($func && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ('delLog' == $func) {
    // close logger, to free remaining file-handles to syslog
    // so we can safely delete the file
    rex_logger::close();

    if (rex_log_file::delete($logFile)) {
        $success = rex_i18n::msg('syslog_deleted');
    } else {
        $error = rex_i18n::msg('syslog_delete_error');
    }
} elseif ('download' == $func && is_file($logFile)) {
    rex_response::sendFile($logFile, 'application/octet-stream', 'attachment');
    exit;
}

if ('' != $success) {
    echo rex_view::success($success);
}

if ('' != $error) {
    echo rex_view::error($error);
}

$content = '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>' . rex_i18n::msg('syslog_timestamp') . '</th>
                        <th>' . rex_i18n::msg('syslog_message') . '</th>
                    </tr>
                </thead>
                <tbody>';

$editor = rex_editor::factory();

$file = rex_log_file::factory($logFile);
foreach (new LimitIterator($file, 0, 100) as $entry) {
    /** @var rex_log_entry $entry */
    $data = $entry->getData();

    $type = rex_type::string($data[0] ?? '');
    $message = rex_type::string($data[1] ?? '');
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
        $path = rex_escape($file . (null === $line ? '' : ':' . $line));

        $fullPath = str_starts_with($file, 'rex://') ? $file : rex_path::base($file);
        if ($url = $editor->getUrl($fullPath, (int) ($line ?? 1))) {
            $path = '<a href="' . $url . '">' . $path . '</a>';
        }
        $path = '<small class="rex-word-break"><span class="label label-default">' . rex_i18n::msg('syslog_file') . ':</span> ' . $path . '</small><br>';
    }

    $url = $data[4] ?? null;
    if ($url) {
        $url = rex_escape($url);
        $url = '<small class="rex-word-break"><span class="label label-default">' . rex_i18n::msg('syslog_url') . ':</span> <a href="' . $url . '">' . $url . '</a></small>';
    } else {
        $url = '';
    }

    $content .= '
                <tr>
                    <td data-title="' . rex_i18n::msg('syslog_timestamp') . '" class="rex-table-tabular-nums rex-table-date">
                        <small>' . rex_formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</small><br>
                        <span class="label label-' . $class . '">' . rex_escape($type) . '</span>
                    </td>
                    <td data-title="' . rex_i18n::msg('syslog_message') . '">
                        <div class="rex-word-break"><b style="font-weight: 500">' . nl2br(rex_escape($message)) . '</b></div>
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
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . rex_i18n::msg('delete') . '?">' . rex_i18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

if ($url = $editor->getUrl($logFile, 0)) {
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '">' . rex_i18n::msg('system_editor_open_file', rex_path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

if (is_file($logFile)) {
    $url = rex_url::currentBackendPage(['func' => 'download'] + $csrfToken->getUrlParams());
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '" download>' . rex_i18n::msg('syslog_download', rex_path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('syslog_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <input type="hidden" name="func" value="delLog" />
        ' . $csrfToken->getHiddenField() . '
        ' . $content . '
    </form>';

echo $content;
