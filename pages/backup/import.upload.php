<?php

use Redaxo\Core\Backup\Backup;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

$success = '';
$error = '';

// ------------------------------ Requestvars
$function = Request::request('function', 'string');
$impname = Request::request('impname', 'string');

@set_time_limit(0);

if ('' != $impname) {
    $impname = Path::basename($impname);
    $validDump = Backup::isFilenameValid(Backup::IMPORT_DB, $impname);
    $validArchive = Backup::isFilenameValid(Backup::IMPORT_ARCHIVE, $impname);

    if ('dbimport' == $function && !$validDump) {
        $impname = '';
    } elseif ('fileimport' == $function && !$validArchive) {
        $impname = '';
    } elseif (('delete' == $function || 'download' == $function) && !$validDump && !$validArchive) {
        $impname = '';
    }
}

if ('download' == $function && $impname && is_readable(Backup::getDir() . '/' . $impname)) {
    Response::sendFile(Backup::getDir() . '/' . $impname, str_ends_with($impname, '.gz') ? 'application/gzip' : 'plain/text', 'attachment');
    exit;
}

$csrfToken = CsrfToken::factory('backup_import');

if ($function && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('delete' == $function && $impname) {
    // ------------------------------ FUNC DELETE
    if (File::delete(Backup::getDir() . '/' . $impname)) {
        $success = I18n::msg('backup_file_deleted');
    } else {
        $error = I18n::msg('backup_file_error_while_delete');
    }
} elseif ('dbimport' == $function) {
    // ------------------------------ FUNC DBIMPORT

    // noch checken das nicht alle tabellen geloescht werden
    // install/temp.sql aendern
    if (isset($_FILES['FORM']) && $_FILES['FORM']['size']['importfile'] < 1 && '' == $impname) {
        $error = I18n::msg('backup_no_import_file_chosen_or_wrong_version') . '<br>';
    } else {
        if ('' != $impname) {
            $fileTemp = Backup::getDir() . '/' . $impname;
        } else {
            $fileTemp = Backup::getDir() . '/temp.sql';
        }

        if ('' != $impname || @move_uploaded_file($_FILES['FORM']['tmp_name']['importfile'], $fileTemp)) {
            $state = Backup::importDb($fileTemp);
            if ($state['state']) {
                $success = $state['message'];
            } else {
                $error = $state['message'];
            }

            // temp datei löschen
            if ('' == $impname) {
                File::delete($fileTemp);
            }
        } else {
            $error = I18n::msg('backup_file_could_not_be_uploaded') . ' ' . I18n::msg('backup_you_have_no_write_permission_in', 'data/core/backup/') . ' <br>';
        }
    }
} elseif ('fileimport' == $function) {
    // ------------------------------ FUNC FILEIMPORT

    if (isset($_FILES['FORM']) && $_FILES['FORM']['size']['importfile'] < 1 && '' == $impname) {
        $error = I18n::msg('backup_no_import_file_chosen') . '<br/>';
    } else {
        if ('' == $impname) {
            $fileTemp = Backup::getDir() . '/temp.tar.gz';
        } else {
            $fileTemp = Backup::getDir() . '/' . $impname;
        }

        if ('' != $impname || @move_uploaded_file($_FILES['FORM']['tmp_name']['importfile'], $fileTemp)) {
            $return = Backup::importFiles($fileTemp);
            if ($return['state']) {
                $success = $return['message'];
            } else {
                $error = $return['message'];
            }

            // temp datei löschen
            if ('' == $impname) {
                File::delete($fileTemp);
            }
        } else {
            $error = I18n::msg('backup_file_could_not_be_uploaded') . ' ' . I18n::msg('backup_you_have_no_write_permission_in', 'data/core/backup/') . ' <br>';
        }
    }
}
if ('' != $success) {
    echo Message::success($success);
}
if ('' != $error) {
    echo Message::error($error);
}

$body = '<p>' . I18n::msg('backup_intro_import') . '</p>';
$body .= '<p>' . I18n::msg('backup_intro_import_files') . '</p>';
$body .= '<p>' . I18n::msg('backup_version_warning') . '</p>';

$body .= ' <hr><p>
                <strong>' . I18n::msg('phpini_settings') . '</strong>
                        <dl class="dl-horizontal text-left">
                        ' . ((0 == ini_get('file_uploads')) ? '<dt><span class="text-danger">' . I18n::msg('backup_warning') . '</span></dt><dd><span class="text-danger">' . I18n::msg('backup_upload_disabled') . '</span></dd>' : '') . '
                            <dt>' . I18n::msg('backup_max_uploadsize') . ':</dt><dd>' . Formatter::bytes(ini_get('upload_max_filesize')) . '</dd>
                            <dt>' . I18n::msg('backup_max_uploadtime') . ':</dt><dd>' . ini_get('max_input_time') . 's</dd>
                        </dl>
            </p>';

$fragment = new Fragment();
$fragment->setVar('class', 'info');
$fragment->setVar('title', I18n::msg('backup_note'), false);
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');

$content = '
    <fieldset>
        <input type="hidden" name="function" value="dbimport" />';

$formElements = [];
$n = [];
$n['label'] = '<label for="rex-form-importdbfile">' . I18n::msg('backup_file') . '</label>';
$n['field'] = '<input type="file" id="rex-form-importdbfile" name="FORM[importfile]" size="18" />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" value="' . I18n::msg('backup_to_import') . '"><i class="rex-icon rex-icon-import"></i> ' . I18n::msg('backup_to_import') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '</fieldset>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('backup_export_db_caption'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . Url::currentBackendPage() . '" enctype="multipart/form-data" method="post" data-confirm="' . I18n::msg('backup_proceed_db_import') . '">
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;

// echo '<h2>' . I18n::msg('backup_files') . '</h2>';

$content = '<fieldset>
                <input type="hidden" name="function" value="fileimport" />';

$formElements = [];
$n = [];
$n['label'] = '<label for="rex-form-importtarfile">' . I18n::msg('backup_file') . '</label>';
$n['field'] = '<input type="file" id="rex-form-importtarfile" name="FORM[importfile]" size="18" />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" value="' . I18n::msg('backup_to_import') . '"><i class="rex-icon rex-icon-import"></i> ' . I18n::msg('backup_to_import') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '</fieldset>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('backup_export_file_caption'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . Url::currentBackendPage() . '" enctype="multipart/form-data" method="post" data-confirm="' . I18n::msg('backup_proceed_file_import') . '" >
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;
