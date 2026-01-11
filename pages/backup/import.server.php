<?php

use Redaxo\Core\Backup\Backup;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

$success = '';
$error = '';

// ------------------------------ Requestvars
$function = Request::request('function', 'string');
$impname = Request::request('impname', 'string');

@set_time_limit(0);

$csrfToken = CsrfToken::factory('backup_import');

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

$fragment = new Fragment();
$fragment->setVar('class', 'info');
$fragment->setVar('title', I18n::msg('backup_note'), false);
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');

$content = '<table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon"></th>
                        <th>' . I18n::msg('backup_filename') . '</th>
                        <th class="rex-table-width-5">' . I18n::msg('backup_filesize') . '</th>
                        <th class="rex-table-width-5">' . I18n::msg('backup_createdate') . '</th>
                        <th class="rex-table-action" colspan="3">' . I18n::msg('backup_function') . '</th>
                    </tr>
                </thead>
                <tbody>';

$dir = Backup::getDir();
$folder = Backup::getBackupFiles(Backup::IMPORT_DB);

foreach ($folder as $file) {
    $filepath = $dir . '/' . $file;
    $filec = date('d.m.Y H:i', filemtime($filepath));
    $filesize = File::formattedSize($filepath);

    $content .= '<tr>
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-database"></i></td>
                    <td data-title="' . I18n::msg('backup_filename') . '">' . $file . '</td>
                    <td data-title="' . I18n::msg('backup_filesize') . '">' . $filesize . '</td>
                    <td data-title="' . I18n::msg('backup_createdate') . '">' . $filec . '</td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'dbimport', 'impname' => $file] + $csrfToken->getUrlParams()) . '" title="' . I18n::msg('backup_import_file') . '" data-confirm="' . I18n::msg('backup_proceed_db_import') . '"><i class="rex-icon rex-icon-import"></i> ' . I18n::msg('backup_to_import') . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'download', 'impname' => $file]) . '" download title="' . I18n::msg('backup_download_file') . '"><i class="rex-icon rex-icon-download"></i> ' . I18n::msg('backup_download') . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'delete', 'impname' => $file] + $csrfToken->getUrlParams()) . '" title="' . I18n::msg('backup_delete_file') . '" data-confirm="' . I18n::msg('backup_delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('backup_delete') . '</a></td>
                </tr>
    ';
}

$content .= '
                    </tbody>
                </table>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('backup_export_db_caption'), false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

echo $content;

$content = '<table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon"></th>
                        <th>' . I18n::msg('backup_filename') . '</th>
                        <th class="rex-table-width-5">' . I18n::msg('backup_filesize') . '</th>
                        <th class="rex-table-width-5">' . I18n::msg('backup_createdate') . '</th>
                        <th class="rex-table-action" colspan="3">' . I18n::msg('backup_function') . '</th>
                    </tr>
                </thead>
                <tbody>';

$dir = Backup::getDir();
$folder = Backup::getBackupFiles(Backup::IMPORT_ARCHIVE);

foreach ($folder as $file) {
    $filepath = $dir . '/' . $file;
    $filec = date('d.m.Y H:i', filemtime($filepath));
    $filesize = File::formattedSize($filepath);

    $content .= '<tr>
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-file-archive"></i></td>
                    <td data-title="' . I18n::msg('backup_filename') . '">' . $file . '</td>
                    <td data-title="' . I18n::msg('backup_filesize') . '">' . $filesize . '</td>
                    <td data-title="' . I18n::msg('backup_createdate') . '">' . $filec . '</td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'fileimport', 'impname' => $file] + $csrfToken->getUrlParams()) . '" title="' . I18n::msg('backup_import_file') . '" data-confirm="' . I18n::msg('backup_proceed_file_import') . '"><i class="rex-icon rex-icon-import"></i> ' . I18n::msg('backup_to_import') . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'download', 'impname' => $file]) . '" download title="' . I18n::msg('backup_download_file') . '"><i class="rex-icon rex-icon-download"></i> ' . I18n::msg('backup_download') . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'delete', 'impname' => $file] + $csrfToken->getUrlParams()) . '" title="' . I18n::msg('backup_delete_file') . '" data-confirm="' . I18n::msg('backup_delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('backup_delete') . '</a></td>
                </tr>';
}

$content .= '
               </tbody>
            </table>
        ';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('backup_export_file_caption'), false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
