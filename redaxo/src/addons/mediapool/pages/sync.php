<?php

assert(isset($rexFileCategory) && is_int($rexFileCategory));

$csrf = rex_csrf_token::factory('mediapool');

// ----- SYNC DB WITH FILES DIR

// ---- Dateien aus dem Ordner lesen
$folderFiles = [];
$path = rex_path::media();
$iterator = rex_finder::factory($path)->filesOnly()->ignoreFiles(['.*', rex::getTempPrefix() . '*'])->sort();
foreach ($iterator as $file) {
    $folderFiles[] = rex_string::normalizeEncoding($file->getFilename());
}

// ---- Dateien aus der DB lesen
$db = rex_sql::factory();
$db->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media');
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
    $path = rex_path::media($filename);
    if (!is_file($path)) {
        continue;
    }

    $fileFilesize = filesize($path);
    if ($dbFile['filesize'] != $fileFilesize) {
        $fileSql = rex_sql::factory();
        $fileSql->setTable(rex::getTable('media'));
        $fileSql->setWhere(['filename' => $filename]);
        $fileSql->setValue('filesize', $fileFilesize);
        if ($dbFile['width'] > 0) {
            if ($size = @getimagesize(rex_path::media($filename))) {
                $fileSql->setValue('width', $size[0]);
                $fileSql->setValue('height', $size[1]);
            }
        }
        $fileSql->update();
        rex_media_cache::delete($filename);
    }
}

$error = [];
$success = [];
if (rex_post('save', 'boolean') && rex_post('sync_files', 'boolean')) {
    if (!$csrf->isValid()) {
        $error[] = rex_i18n::msg('csrf_token_invalid');
    } else {
        $syncFiles = rex_post('sync_files', 'array[string]');
        $ftitle = rex_post('ftitle', 'string');

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
                    'path' => rex_path::media($filename),
                ];

                try {
                    rex_media_service::addMedia($data, false);

                    unset($diffFiles[$key]);
                    if ($first) {
                        $success[] = rex_i18n::msg('pool_sync_files_synced');
                        $first = false;
                    }
                } catch (rex_api_exception $e) {
                    $error[] = $e->getMessage();
                }
            }
            // diff count neu berechnen, da (hoffentlich) diff files in die db geladen wurden
            $diffCount = count($diffFiles);
        }
    }
} elseif (rex_post('save', 'boolean')) {
    $error[] = rex_i18n::msg('pool_file_not_found');
}

if (count($error) > 0) {
    echo rex_view::error(implode('<br />', $error));
    $error = [];
}
if (count($success) > 0) {
    echo rex_view::info(implode('<br />', $success));
    $success = [];
}

$content = '';

if ($diffCount > 0) {
    $writable = [];
    $notWritable = [];
    foreach ($diffFiles as $file) {
        if (is_writable(rex_path::media($file))) {
            $e = [];
            $e['label'] = '<label>' . rex_escape($file) . '</label>';
            $e['field'] = '<input type="checkbox" name="sync_files[]" value="' . rex_escape($file) . '" />';
            $writable[] = $e;
        } else {
            $notWritable[] = rex_escape($file);
        }
    }

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_select_all') . '</label>';
    $e['field'] = '<input type="checkbox" name="checkie" id="rex-js-checkie" value="0" onchange="setAllCheckBoxes(\'sync_files[]\',this)" />';
    $writable[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $writable, false);
    $panel = $fragment->parse('core/form/checkbox.php');

    $count = count($writable) - 1;
    if ($count) {
        $content .= rex_mediapool_Mediaform(rex_i18n::msg('pool_sync_title'), rex_i18n::msg('pool_sync_button'), $rexFileCategory, false, false);
        $content .= '<fieldset>';

        $title = rex_i18n::msg('pool_sync_affected_files') . ' (' . $count . ')';

        $fragment = new rex_fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', $panel, false);
        $content .= $fragment->parse('core/page/section.php');

        $content .= '
                </fieldset>
            </form>

            <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
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
        $title = $count > 1 ? rex_i18n::msg('pool_files_not_writable') : rex_i18n::msg('pool_file_not_writable');

        $fragment = new rex_fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', '<ul><li>' . implode('</li><li>', $notWritable) . '</li></ul>', false);
        $fragment->setVar('class', 'warning', false);
        $content .= $fragment->parse('core/page/section.php');
    }
} else {
    $panel = '<p>' . rex_i18n::msg('pool_sync_no_diffs') . '</p>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('pool_sync_title'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('class', 'info', false);
    $content = $fragment->parse('core/page/section.php');
}

echo $content;
