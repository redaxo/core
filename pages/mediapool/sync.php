<?php

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPoolCache;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

assert(isset($rexFileCategory) && is_int($rexFileCategory));

$csrf = CsrfToken::factory('mediapool');

// ----- SYNC DB WITH FILES DIR

// ---- Dateien aus dem Ordner lesen
$folderFiles = [];
$path = Path::media();
$iterator = Finder::factory($path)->filesOnly()->ignoreFiles(['.*', Core::getTempPrefix() . '*'])->sort();
foreach ($iterator as $file) {
    $folderFiles[] = Str::normalizeEncoding($file->getFilename());
}

// ---- Dateien aus der DB lesen
$db = Sql::factory();
$db->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media');
$dbFiles = [];
$dbFilenames = [];

foreach ($db->getArray() as $dbFile) {
    $dbFilenames[] = (string) $dbFile['filename'];
    $dbFiles[] = $dbFile;
}

$diffFiles = array_diff($folderFiles, $dbFilenames);
$diffCount = count($diffFiles);

// Extra - filesize/width/height DB-Filesystem Sync
foreach ($dbFiles as $dbFile) {
    $filename = (string) $dbFile['filename'];
    $path = Path::media($filename);
    if (!is_file($path)) {
        continue;
    }

    $fileFilesize = filesize($path);
    if ($dbFile['filesize'] != $fileFilesize) {
        $fileSql = Sql::factory();
        $fileSql->setTable(Core::getTable('media'));
        $fileSql->setWhere(['filename' => $filename]);
        $fileSql->setValue('filesize', $fileFilesize);
        if ($dbFile['width'] > 0) {
            if ($size = @getimagesize(Path::media($filename))) {
                $fileSql->setValue('width', $size[0]);
                $fileSql->setValue('height', $size[1]);
            }
        }
        $fileSql->update();
        MediaPoolCache::delete($filename);
    }
}

$error = [];
$success = [];
if (Request::post('save', 'boolean') && Request::post('sync_files', 'boolean')) {
    if (!$csrf->isValid()) {
        $error[] = I18n::msg('csrf_token_invalid');
    } else {
        $syncFiles = Request::post('sync_files', 'array[string]');
        $ftitle = Request::post('ftitle', 'string');

        if ($diffCount > 0) {
            $success = [];
            $first = true;
            foreach ($syncFiles as $filename) {
                if (false === $key = array_search($filename, $diffFiles)) {
                    continue;
                }

                $data = [];
                $data['title'] = $ftitle;
                $data['category_id'] = $rexFileCategory;
                $data['filename'] = $filename;
                $data['file'] = [
                    'name' => $filename,
                    'path' => Path::media($filename),
                ];

                try {
                    MediaHandler::addMedia($data, false);

                    unset($diffFiles[$key]);
                    if ($first) {
                        $success[] = I18n::msg('pool_sync_files_synced');
                        $first = false;
                    }
                } catch (ApiFunctionException $e) {
                    $error[] = $e->getMessage();
                }
            }
            // diff count neu berechnen, da (hoffentlich) diff files in die db geladen wurden
            $diffCount = count($diffFiles);
        }
    }
} elseif (Request::post('save', 'boolean')) {
    $error[] = I18n::msg('pool_file_not_found');
}

if (count($error) > 0) {
    echo Message::error(implode('<br />', $error));
    $error = [];
}
if (count($success) > 0) {
    echo Message::info(implode('<br />', $success));
    $success = [];
}

$content = '';

if ($diffCount > 0) {
    $writable = [];
    $notWritable = [];
    foreach ($diffFiles as $file) {
        if (is_writable(Path::media($file))) {
            $e = [];
            $e['label'] = '<label>' . $file . '</label>';
            $e['field'] = '<input type="checkbox" name="sync_files[]" value="' . $file . '" />';
            $writable[] = $e;
        } else {
            $notWritable[] = $file;
        }
    }

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_select_all') . '</label>';
    $e['field'] = '<input type="checkbox" name="checkie" id="rex-js-checkie" value="0" onchange="setAllCheckBoxes(\'sync_files[]\',this)" />';
    $writable[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $writable, false);
    $panel = $fragment->parse('core/form/checkbox.php');

    $count = count($writable) - 1;
    if ($count) {
        $content .= View::mediaPoolMediaForm(I18n::msg('pool_sync_title'), I18n::msg('pool_sync_button'), $rexFileCategory, false, false);
        $content .= '<fieldset>';

        $title = I18n::msg('pool_sync_affected_files') . ' (' . $count . ')';

        $fragment = new Fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', $panel, false);
        $content .= $fragment->parse('core/page/section.php');

        $content .= '
                </fieldset>
            </form>

            <script type="text/javascript" nonce="' . Response::getNonce() . '">
                jQuery(document).ready(function($){
                    $("input[name=\'sync_files[]\']").change(function() {
                        $(this).closest(\'form\').find("[type=\'submit\']").attr("disabled", $("input[name=\'sync_files[]\']:checked").length == 0);
                    }).change();
                    $("#rex-js-checkie").change(function() {
                        $("input[name=\'sync_files[]\']").change();
                    });
                });
            </script>';
    }

    $count = count($notWritable);
    if ($count) {
        $title = $count > 1 ? I18n::msg('pool_files_not_writable') : I18n::msg('pool_file_not_writable');

        $fragment = new Fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', '<ul><li>' . implode('</li><li>', $notWritable) . '</li></ul>', false);
        $fragment->setVar('class', 'warning', false);
        $content .= $fragment->parse('core/page/section.php');
    }
} else {
    $panel = '<p>' . I18n::msg('pool_sync_no_diffs') . '</p>';

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('pool_sync_title'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('class', 'info', false);
    $content = $fragment->parse('core/page/section.php');
}

echo $content;
