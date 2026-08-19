<?php

/**
 * Cronjob Addon.
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 */

$func = rex_request('func', 'string');
$error = '';
$success = '';
$message = '';
$logFile = rex_path::log('cronjob.log');

$csrfToken = rex_csrf_token::factory('cronjob_log');

if ('cronjob_delLog' == $func && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ('cronjob_delLog' == $func) {
    if (rex_log_file::delete($logFile)) {
        $success = rex_i18n::msg('syslog_deleted');
    } else {
        $error = rex_i18n::msg('syslog_delete_error');
    }
}
if ('' != $success) {
    $message .= rex_view::success($success);
}
if ('' != $error) {
    $message .= rex_view::error($error);
}
$content = '';

$content .= '
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon"></th>
                        <th>' . rex_i18n::msg('cronjob_log_date') . '</th>
                        <th>' . rex_i18n::msg('cronjob_name') . '</th>
                        <th>' . rex_i18n::msg('cronjob_log_message') . '</th>
                        <th>' . rex_i18n::msg('cronjob_environment') . '</th>
                    </tr>
                </thead>
                <tbody>';

$formElements = [];

$file = rex_log_file::factory($logFile);

/** @var rex_log_entry $entry */
foreach (new LimitIterator($file, 0, 100) as $entry) {
    $data = $entry->getData();
    $class = 'ERROR' == trim($data[0]) ? 'rex-state-error' : 'rex-state-success';
    if ('--' == $data[1]) {
        $icon = '<i class="rex-icon rex-icon-cronjob" title="' . rex_i18n::msg('cronjob_not_editable') . '"></i>';
    } else {
        $icon = '<a href="' . rex_url::backendPage('cronjob', ['list' => 'cronjobs', 'func' => 'edit', 'oid' => $data[1]]) . '" title="' . rex_i18n::msg('cronjob_edit') . '"><i class="rex-icon rex-icon-cronjob"></i></a>';
    }
    $content .= '
        <tr class="' . $class . '">
            <td class="rex-table-icon">' . $icon . '</td>
            <td data-title="' . rex_i18n::msg('cronjob_log_date') . '" class="rex-table-tabular-nums">' . rex_formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</td>
            <td data-title="' . rex_i18n::msg('cronjob_name') . '">' . rex_escape($data[2]) . '</td>
            <td data-title="' . rex_i18n::msg('cronjob_log_message') . '">' . nl2br(rex_escape($data[3])) . '</td>
            <td data-title="' . rex_i18n::msg('cronjob_environment') . '">' . (isset($data[4]) ? rex_i18n::msg('cronjob_environment_' . $data[4]) : '') . '</td>
        </tr>';
}

// XXX calc last line and use it instead
if ($url = rex_editor::factory()->getUrl($logFile, 1)) {
    $n = [];
    $n['field'] = '<a class="btn btn-save" href="' . $url . '">' . rex_i18n::msg('system_editor_open_file', rex_path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

$n = [];
$n['field'] = '<button class="btn btn-delete" type="submit" name="del_btn" data-confirm="' . rex_i18n::msg('cronjob_delete_log_msg') . '?">' . rex_i18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '
                </tbody>
            </table>';

$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        <input type="hidden" name="func" value="cronjob_delLog" />
        ' . $content . '
    </form>';

echo $message;
echo $content;
