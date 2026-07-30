<?php

$func = rex_request('func', 'string');
$error = '';
$success = '';
$logFile = rex_mailer::logFile();

$csrfToken = rex_csrf_token::factory('phpmailer-delete-log');

if ('mailer_delLog' == $func && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ('mailer_delLog' == $func) {
    if (rex_log_file::delete($logFile)) {
        $success = rex_i18n::msg('syslog_deleted');
    } else {
        $error = rex_i18n::msg('syslog_delete_error');
    }
}
$message = '';
if ('' != $success) {
    $message .= rex_view::success($success);
}
if ('' != $error) {
    $message .= rex_view::error($error);
}

$content = '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>' . rex_i18n::msg('phpmailer_log_success') . '</th>
                        <th>' . rex_i18n::msg('phpmailer_log_date') . '</th>
                        <th>' . rex_i18n::msg('phpmailer_log_from') . '</th>
                        <th>' . rex_i18n::msg('phpmailer_log_to') . '</th>
                        <th>' . rex_i18n::msg('phpmailer_log_subject') . '</th>
                        <th>' . rex_i18n::msg('phpmailer_log_msg') . '</th>
                    </tr>
                </thead>
                <tbody>';

$file = rex_log_file::factory($logFile);
foreach (new LimitIterator($file, 0, 30) as $entry) {
    $data = $entry->getData();
    $class = 'ERROR' == trim($data[0]) ? 'rex-state-error' : 'rex-mailer-log-ok';
    $content .= '
                <tr class="' . $class . '">
                  <td data-title="' . rex_i18n::msg('phpmailer_log_success') . '"><strong>' . rex_escape($data[0]) . '</strong></td>
                  <td data-title="' . rex_i18n::msg('phpmailer_log_date') . '" class="rex-table-tabular-nums">' . rex_formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</td>
                  <td data-title="' . rex_i18n::msg('phpmailer_log_from') . '">' . rex_escape($data[1]) . '</td>
                  <td data-title="' . rex_i18n::msg('phpmailer_log_to') . '">' . rex_escape($data[2]) . '</td>
                  <td data-title="' . rex_i18n::msg('phpmailer_log_subject') . '">' . rex_escape($data[3]) . '</td>
                  <td data-title="' . rex_i18n::msg('phpmailer_log_msg') . '">' . nl2br(rex_escape($data[4])) . '</td>
                </tr>';
}

$content .= '
                </tbody>
            </table>';

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . rex_i18n::msg('phpmailer_delete_log_msg') . '">' . rex_i18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('phpmailer_log_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');
$content = '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        <input type="hidden" name="func" value="mailer_delLog" />
        ' . $content . '
    </form>';
echo $message;
echo $content;
