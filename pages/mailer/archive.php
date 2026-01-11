<?php

use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

/**
 * PHPMailer Archive Management.
 */

$func = Request::request('func', 'string');
$message = '';

// Handle archive deletion
if ('delete_archive' == $func) {
    if (CsrfToken::factory('phpmailer-delete-archive')->isValid()) {
        if (Dir::delete(Mailer::logFolder(), true)) {
            $message = Message::success(I18n::msg('phpmailer_archive_deleted'));
        } else {
            $message = Message::error(I18n::msg('phpmailer_archive_delete_error'));
        }
    } else {
        $message = Message::error(I18n::msg('csrf_token_invalid'));
    }
}

$archiveFolder = Mailer::logFolder();
$archiveExists = is_dir($archiveFolder);

// Two-column layout for Archive Information and Archive Maintenance
echo '<div class="row">';

// Archive Information Panel (left column)
echo '<div class="col-md-6">';
$content = '';

// Archive Status Panel
$formElements = [];
$n = [];
$n['label'] = '<label>' . I18n::msg('phpmailer_archive_path') . '</label>';
$n['field'] = '<span style="word-break: break-all; font-family: monospace; font-size: 0.9em;">' . escape($archiveFolder) . '</span>';
$formElements[] = $n;

if ($archiveExists) {
    // Get archive statistics
    $archiveStats = Mailer::getArchiveStats();

    $n = [];
    $n['label'] = '<label>' . I18n::msg('phpmailer_archive_size') . '</label>';
    $n['field'] = Formatter::bytes($archiveStats['size']);
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label>' . I18n::msg('phpmailer_archive_file_count') . '</label>';
    $n['field'] = $archiveStats['fileCount'] . ' ' . I18n::msg('phpmailer_archive_files');
    $formElements[] = $n;
} else {
    $n = [];
    $n['label'] = '<label>' . I18n::msg('phpmailer_archive_status') . '</label>';
    $n['field'] = '<span class="text-muted">' . I18n::msg('phpmailer_archive_empty') . '</span>';
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$fragment->setVar('grouped', true);
$content .= $fragment->parse('core/form/form.php');

$fragment = new Fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', I18n::msg('phpmailer_archive_info'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
echo '</div>';

// Archive Maintenance Panel (right column)
echo '<div class="col-md-6">';
$content = '';
$content .= '<p>' . I18n::msg('phpmailer_archive_purge_info') . '</p>';

// Add archive management to maintenance panel if archive exists
if ($archiveExists) {
    $content .= '<hr>';
    $content .= '<div>';
    $content .= '<strong>' . I18n::msg('phpmailer_archive_delete_warning') . '</strong><br>';
    $content .= I18n::msg('phpmailer_archive_delete_warning_desc');
    $content .= '</div>';

    $content .= '<form method="post" action="' . Url::currentBackendPage() . '">';
    $content .= CsrfToken::factory('phpmailer-delete-archive')->getHiddenField();
    $content .= '<input type="hidden" name="func" value="delete_archive">';
    $content .= '<button type="submit" class="btn btn-danger btn-sm" data-confirm="' . I18n::msg('phpmailer_archive_delete_confirm') . '">';
    $content .= '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('phpmailer_archive_delete');
    $content .= '</button>';
    $content .= '</form>';
}

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('phpmailer_archive_maintenance'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
echo '</div>';

// Close row
echo '</div>';

// Recent Archived Emails Panel
if ($archiveExists) {
    $content = '';
    $content .= '<table class="table table-hover">';
    $content .= '<thead>';
    $content .= '<tr>';
    $content .= '<th>' . I18n::msg('phpmailer_archive_date') . '</th>';
    $content .= '<th>' . I18n::msg('phpmailer_archive_subject') . '</th>';
    $content .= '<th>' . I18n::msg('phpmailer_archive_to') . '</th>';
    $content .= '<th>' . I18n::msg('phpmailer_archive_size') . '</th>';
    $content .= '</tr>';
    $content .= '</thead>';
    $content .= '<tbody>';

    // Get recent .eml files using rex_mailer method
    $recentFiles = Mailer::getRecentArchivedFiles(10);

    if ($recentFiles) {
        /** @var string $file */
        foreach ($recentFiles as $file) {
            // Parse email headers using rex_mailer method
            $emailData = Mailer::parseEmailHeaders($file);

            $content .= '<tr>';
            $content .= '<td data-title="' . I18n::msg('phpmailer_archive_date') . '" class="rex-table-tabular-nums">' . Formatter::intlDateTime($emailData['mtime'], [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]) . '</td>';
            $content .= '<td data-title="' . I18n::msg('phpmailer_archive_subject') . '">' . escape($emailData['subject']) . '</td>';
            $content .= '<td data-title="' . I18n::msg('phpmailer_archive_to') . '">' . escape($emailData['recipient']) . '</td>';
            $content .= '<td data-title="' . I18n::msg('phpmailer_archive_size') . '" class="rex-table-tabular-nums">' . Formatter::bytes($emailData['size']) . '</td>';
            $content .= '</tr>';
        }
    } else {
        $content .= '<tr><td colspan="4" class="text-muted text-center">' . I18n::msg('phpmailer_archive_no_files') . '</td></tr>';
    }

    $content .= '</tbody>';
    $content .= '</table>';

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('phpmailer_archive_recent_mails'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}

echo $message;
