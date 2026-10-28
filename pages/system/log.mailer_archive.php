<?php

use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Mailer\MailArchive;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Component\TableRow;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$message = '';

// Handle archive deletion
if ('delete_archive' == $func) {
    if (CsrfToken::factory('mailer-delete-archive')->isValid()) {
        if (Dir::delete(MailArchive::folder(), true)) {
            $message = Message::success(I18n::msg('mailer_archive_deleted'));
        } else {
            $message = Message::error(I18n::msg('mailer_archive_delete_error'));
        }
    } else {
        $message = Message::error(I18n::msg('csrf_token_invalid'));
    }
}

$archiveFolder = MailArchive::folder();
$archiveExists = is_dir($archiveFolder);

// Two-column layout for Archive Information and Archive Maintenance
echo '<div class="row">';

// Archive Information Panel (left column)
echo '<div class="col-md-6">';
$content = '';

// Archive Status Panel
$formElements = [];
$n = [];
$n['label'] = '<label>' . I18n::msg('mailer_archive_path') . '</label>';
$n['field'] = '<code class="rex-word-break">' . escape($archiveFolder) . '</code>';
$formElements[] = $n;

if ($archiveExists) {
    // Get archive statistics
    $archiveStats = MailArchive::getStats();

    $n = [];
    $n['label'] = '<label>' . I18n::msg('mailer_archive_size') . '</label>';
    $n['field'] = Formatter::bytes($archiveStats['size']);
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label>' . I18n::msg('mailer_archive_file_count') . '</label>';
    $n['field'] = $archiveStats['fileCount'] . ' ' . I18n::msg('mailer_archive_files');
    $formElements[] = $n;
} else {
    $n = [];
    $n['label'] = '<label>' . I18n::msg('mailer_archive_status') . '</label>';
    $n['field'] = '<span class="text-muted">' . I18n::msg('mailer_archive_empty') . '</span>';
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$fragment->setVar('grouped', true);
$content .= $fragment->parse('core/form/form.php');

$fragment = new Fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', I18n::msg('mailer_archive_info'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
echo '</div>';

// Archive Maintenance Panel (right column)
echo '<div class="col-md-6">';
$content = '';
$content .= '<p>' . I18n::msg('mailer_archive_purge_info') . '</p>';

// Add archive management to maintenance panel if archive exists
if ($archiveExists) {
    $content .= '<hr>';
    $content .= '<div>';
    $content .= '<strong>' . I18n::msg('mailer_archive_delete_warning') . '</strong><br>';
    $content .= I18n::msg('mailer_archive_delete_warning_desc');
    $content .= '</div>';

    $content .= '<form method="post" action="' . Url::currentBackendPage() . '">';
    $content .= CsrfToken::factory('mailer-delete-archive')->getHiddenField();
    $content .= '<input type="hidden" name="func" value="delete_archive">';
    $content .= '<button type="submit" class="rex-button" data-variant="danger" data-size="sm" data-confirm="' . I18n::msg('mailer_archive_delete_confirm') . '">';
    $content .= '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('mailer_archive_delete');
    $content .= '</button>';
    $content .= '</form>';
}

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('mailer_archive_maintenance'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
echo '</div>';

// Close row
echo '</div>';

// Recent Archived Emails Panel
if ($archiveExists) {
    $recentFiles = MailArchive::getRecentFiles(10);

    $rows = [];
    foreach ($recentFiles as $file) {
        $mail = MailArchive::parseHeaders($file);
        $rows[] = [
            'date' => Formatter::intlDateTime($mail['mtime'], [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]),
            'subject' => $mail['subject'],
            'to' => $mail['recipient'],
            'size' => Formatter::bytes($mail['size']),
        ];
    }

    $content = (string) new Table([
        TableColumn::forKey(I18n::msg('mailer_archive_date'), 'date', attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
        TableColumn::forKey(I18n::msg('mailer_archive_subject'), 'subject'),
        TableColumn::forKey(I18n::msg('mailer_archive_to'), 'to'),
        TableColumn::forKey(I18n::msg('mailer_archive_size'), 'size', attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
    ], $rows ?: [new TableRow([new TableCell(I18n::msg('mailer_archive_no_files'), 4, new HtmlAttributes(['class' => ['text-muted', 'text-center']]))])],
        attributes: new HtmlAttributes(['data-hover' => true]))->render();

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('mailer_archive_recent_mails'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}

echo $message;
