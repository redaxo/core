<?php

$success = '';
$error = '';

// ------------------------------ Requestvars
$function = rex_request('function', 'string');
$impname = rex_request('impname', 'string');

@set_time_limit(0);

if ('' != $impname) {
    $impname = rex_path::basename($impname);
    $validDump = rex_backup::isFilenameValid(rex_backup::IMPORT_DB, $impname);
    $validArchive = rex_backup::isFilenameValid(rex_backup::IMPORT_ARCHIVE, $impname);

    if ('dbimport' == $function && !$validDump) {
        $impname = '';
    } elseif ('fileimport' == $function && !$validArchive) {
        $impname = '';
    } elseif (('delete' == $function || 'download' == $function) && !$validDump && !$validArchive) {
        $impname = '';
    }
}

if ('download' == $function && $impname && is_readable(rex_backup::getDir() . '/' . $impname)) {
    rex_response::sendFile(rex_backup::getDir() . '/' . $impname, str_ends_with($impname, '.gz') ? 'application/gzip' : 'plain/text', 'attachment');
    exit;
}

$csrfToken = rex_csrf_token::factory('backup_import');

if ($function && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ('delete' == $function && $impname) {
    // ------------------------------ FUNC DELETE
    if (rex_file::delete(rex_backup::getDir() . '/' . $impname)) {
        $success = rex_i18n::msg('backup_file_deleted');
    } else {
        $error = rex_i18n::msg('backup_file_error_while_delete');
    }
} elseif ('dbimport' == $function) {
    // ------------------------------ FUNC DBIMPORT

    // noch checken das nicht alle tabellen geloescht werden
    // install/temp.sql aendern
    if (isset($_FILES['FORM']) && $_FILES['FORM']['size']['importfile'] < 1 && '' == $impname) {
        $error = rex_i18n::msg('backup_no_import_file_chosen_or_wrong_version') . '<br>';
    } else {
        if ('' != $impname) {
            $fileTemp = rex_backup::getDir() . '/' . $impname;
        } else {
            $fileTemp = rex_backup::getDir() . '/temp.sql';
        }

        if ('' != $impname || @move_uploaded_file($_FILES['FORM']['tmp_name']['importfile'], $fileTemp)) {
            $state = rex_backup::importDb($fileTemp);
            if ($state['state']) {
                $success = $state['message'];
            } else {
                $error = $state['message'];
            }

            // temp datei löschen
            if ('' == $impname) {
                rex_file::delete($fileTemp);
            }
        } else {
            $error = rex_i18n::msg('backup_file_could_not_be_uploaded') . ' ' . rex_i18n::msg('backup_you_have_no_write_permission_in', 'data/addons/backup/') . ' <br>';
        }
    }
} elseif ('fileimport' == $function) {
    // ------------------------------ FUNC FILEIMPORT

    if (isset($_FILES['FORM']) && $_FILES['FORM']['size']['importfile'] < 1 && '' == $impname) {
        $error = rex_i18n::msg('backup_no_import_file_chosen') . '<br/>';
    } else {
        if ('' == $impname) {
            $fileTemp = rex_backup::getDir() . '/temp.tar.gz';
        } else {
            $fileTemp = rex_backup::getDir() . '/' . $impname;
        }

        if ('' != $impname || @move_uploaded_file($_FILES['FORM']['tmp_name']['importfile'], $fileTemp)) {
            $return = rex_backup::importFiles($fileTemp);
            if ($return['state']) {
                $success = $return['message'];
            } else {
                $error = $return['message'];
            }

            // temp datei löschen
            if ('' == $impname) {
                rex_file::delete($fileTemp);
            }
        } else {
            $error = rex_i18n::msg('backup_file_could_not_be_uploaded') . ' ' . rex_i18n::msg('backup_you_have_no_write_permission_in', 'data/addons/backup/') . ' <br>';
        }
    }
}
if ('' != $success) {
    echo rex_view::success($success);
}
if ('' != $error) {
    echo rex_view::error($error);
}

$body = '<p>' . rex_i18n::msg('backup_intro_import') . '</p>';
$body .= '<p>' . rex_i18n::msg('backup_intro_import_files') . '</p>';
$body .= '<p>' . rex_i18n::msg('backup_version_warning') . '</p>';

$body .= ' <hr><p>
                <strong>' . rex_i18n::msg('phpini_settings') . '</strong>
                        <dl class="dl-horizontal text-left">
                        ' . ((0 == rex_ini_get('file_uploads')) ? '<dt><span class="text-danger">' . rex_i18n::msg('backup_warning') . '</span></dt><dd><span class="text-danger">' . rex_i18n::msg('backup_upload_disabled') . '</span></dd>' : '') . '
                            <dt>' . rex_i18n::msg('backup_max_uploadsize') . ':</dt><dd>' . rex_formatter::bytes(rex_request::getMaxUploadSize()) . '</dd>
                            <dt>' . rex_i18n::msg('backup_max_uploadtime') . ':</dt><dd>' . rex_ini_get('max_input_time') . 's</dd>
                        </dl>
            </p>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info');
$fragment->setVar('title', rex_i18n::msg('backup_note'), false);
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');

$content = '
    <fieldset>
        <input type="hidden" name="function" value="dbimport" />';

$formElements = [];
$n = [];
$n['label'] = '<label for="rex-form-importdbfile">' . rex_i18n::msg('backup_file') . '</label>';
$n['field'] = '<input type="file" id="rex-form-importdbfile" name="FORM[importfile]" size="18" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" value="' . rex_i18n::msg('backup_to_import') . '"><i class="rex-icon rex-icon-import"></i> ' . rex_i18n::msg('backup_to_import') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '</fieldset>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('backup_export_db_caption'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" enctype="multipart/form-data" method="post" data-confirm="' . rex_i18n::msg('backup_proceed_db_import') . '">
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;

// echo '<h2>' . rex_i18n::msg('backup_files') . '</h2>';

$content = '<fieldset>
                <input type="hidden" name="function" value="fileimport" />';

$formElements = [];
$n = [];
$n['label'] = '<label for="rex-form-importtarfile">' . rex_i18n::msg('backup_file') . '</label>';
$n['field'] = '<input type="file" id="rex-form-importtarfile" name="FORM[importfile]" size="18" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" value="' . rex_i18n::msg('backup_to_import') . '"><i class="rex-icon rex-icon-import"></i> ' . rex_i18n::msg('backup_to_import') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '</fieldset>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('backup_export_file_caption'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" enctype="multipart/form-data" method="post" data-confirm="' . rex_i18n::msg('backup_proceed_file_import') . '" >
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;
