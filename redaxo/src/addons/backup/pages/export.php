<?php

// Für größere Exports den Speicher für PHP erhöhen.
if (rex_ini_get('memory_limit') < 67_108_864) {
    @ini_set('memory_limit', '64M');
}

$success = '';
$error = '';

// ------------------------------ Requestvars
$exportfilename = rex_post('exportfilename', 'string');
$exporttype = rex_post('exporttype', 'string');
$exportdl = rex_post('exportdl', 'boolean');
$EXPTABLES = rex_post('EXPTABLES', 'array[string]');
$EXPDIR = rex_post('EXPDIR', 'array[string]');

if ('' == $exportfilename) {
    $exportfilename = rex_string::normalize(rex::getServerName()) . '_' . date('Ymd_Hi') . '_rex' . rex::getVersion();
}

$tables = rex_sql::factory()->getTables();
$EXPTABLES = array_intersect($EXPTABLES, $tables);

$dir = rex_path::frontend();
$folders = rex_finder::factory($dir)
    ->dirsOnly()
    ->ignoreDirs('.*')
    ->ignoreDirs('redaxo')
;
$folders = array_keys(iterator_to_array($folders));
$folders = array_map(rex_path::basename(...), $folders);
$EXPDIR = array_intersect($EXPDIR, $folders);

$csrfToken = rex_csrf_token::factory('backup');
$export = rex_post('export', 'bool');

if ($export && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ($export) {
    // ------------------------------ FUNC EXPORT

    $exportfilename = strtolower($exportfilename);
    /**
     * @psalm-taint-escape file
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape html
     * @psalm-taint-escape shell
     */
    $filename = preg_replace('@[^\.a-z0-9_\-]@', '', $exportfilename);

    if ($filename != $exportfilename) {
        $error = rex_i18n::msg('backup_filename_updated');
        $exportfilename = $filename;
    } else {
        $hasContent = false;
        $header = '';
        $ext = 'sql' == $exporttype ? '.sql' : '.tar.gz';
        $exportPath = rex_backup::getDir() . '/';

        if (is_file($exportPath . $filename . $ext)) {
            $i = 1;
            while (is_file($exportPath . $filename . '_' . $i . $ext)) {
                ++$i;
            }
            $filename = $filename . '_' . $i;
        }

        if ('sql' == $exporttype) {
            // ------------------------------ FUNC EXPORT SQL
            $header = 'plain/text';

            $hasContent = rex_backup::exportDb($exportPath . $filename . $ext, $EXPTABLES);
        } elseif ('files' == $exporttype) {
            // ------------------------------ FUNC EXPORT FILES
            $header = 'tar/gzip';

            if (empty($EXPDIR)) {
                $error = rex_i18n::msg('backup_please_choose_folder');
            } else {
                rex_backup::exportFiles($EXPDIR, $exportPath . $filename . $ext);
                $hasContent = true;
            }
        }

        if ($hasContent) {
            if ($exportdl) {
                $filename .= $ext;
                rex_response::sendFile($exportPath . $filename, $header, 'attachment');
                rex_file::delete($exportPath . $filename);
                exit;
            }
            $success = rex_i18n::msg('backup_file_generated_in') . ' ' . strtr($filename . $ext, '\\', '/');
        } elseif (empty($error)) { // if the user selected no files to export $error is already filled
            $error = rex_i18n::msg('backup_file_could_not_be_generated') . ' ' . rex_i18n::msg('backup_check_rights_in_directory') . ' ' . $exportPath;
        }
    }
}

if ('' != $success) {
    echo rex_view::success($success);
}
if ('' != $error) {
    echo rex_view::error($error);
}

$content = '';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', rex_i18n::msg('backup_information'), false);
$fragment->setVar('body', '<p>' . rex_i18n::msg('backup_intro_export') . '</p>', false);
echo $fragment->parse('core/page/section.php');

$content .= '<fieldset>';

$checkedsql = '';
$checkedfiles = '';

if ('files' == $exporttype) {
    $checkedfiles = ' checked="checked"';
} else {
    $checkedsql = ' checked="checked"';
}

$formElements = [];
$n = [];
$n['label'] = '<label for="rex-js-exporttype-sql">' . rex_i18n::msg('backup_database_export') . '</label>';
$n['field'] = '<input type="radio" id="rex-js-exporttype-sql" name="exporttype" value="sql"' . $checkedsql . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-js-exporttype-files">' . rex_i18n::msg('backup_file_export') . '</label>';
$n['field'] = '<input type="radio" id="rex-js-exporttype-files" name="exporttype" value="files"' . $checkedfiles . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$radios = $fragment->parse('core/form/radio.php');

$formElements = [];
$n = [];
$n['label'] = rex_i18n::msg('backup_export_select');
$n['field'] = $radios;
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$tableSelect = new rex_select();
$tableSelect->setMultiple();
$tableSelect->setId('rex-form-exporttables');
$tableSelect->setSize(20);
$tableSelect->setName('EXPTABLES[]');
$tableSelect->setAttribute('class', 'form-control');
foreach ($tables as $table) {
    $tableSelect->addOption($table, $table);
    if (in_array($table, [rex::getTable('user'), rex::getTable('user_passkey'), rex::getTable('user_session')], true)) {
        continue;
    }
    // skip non rex_ tables
    if (!str_starts_with($table, rex::getTablePrefix())) {
        continue;
    }
    // skip rex_tmp_ tables
    if (str_starts_with($table, rex::getTablePrefix() . rex::getTempPrefix())) {
        continue;
    }

    $tableSelect->setSelected($table);
}

$formElements = [];
$n = [];
$n['header'] = '<div id="rex-js-exporttype-sql-div"' . ($checkedsql ? '' : ' style="display: none;"') . '>';
$n['label'] = '<label for="rex-form-exporttables">' . rex_i18n::msg('backup_export_select_tables') . '</label>';
$n['field'] = $tableSelect->get();
$n['footer'] = '</div>';
$formElements[] = $n;

// Vorhandene Exporte auslesen
$selDirs = new rex_select();
$selDirs->setId('rex-form-exportdir');
$selDirs->setName('EXPDIR[]');
$selDirs->setMultiple();
$selDirs->setSelected($EXPDIR);
$selDirs->setStyle('class="form-control"');

$countFolders = count($folders);
if ($countFolders > 4) {
    $selDirs->setSize($countFolders);
}
foreach ($folders as $path) {
    $selDirs->addOption($path, $path);
}

$n = [];
$n['header'] = '<div id="rex-js-exporttype-files-div"' . ($checkedfiles ? '' : ' style="display: none;"') . '>';
$n['label'] = '<label for="rex-form-exportdir">' . rex_i18n::msg('backup_export_select_dir') . '</label>';
$n['field'] = $selDirs->get();
$n['footer'] = '</div>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$checked0 = '';
$checked1 = '';

if ($exportdl) {
    $checked1 = ' checked="checked"';
} else {
    $checked0 = ' checked="checked"';
}

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-form-exportdl-server">' . rex_i18n::msg('backup_save_on_server') . '</label>';
$n['field'] = '<input type="radio" id="rex-form-exportdl-server" name="exportdl" value="0"' . $checked0 . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-exportdl-download">' . rex_i18n::msg('backup_download_as_file') . '</label>';
$n['field'] = '<input type="radio" id="rex-form-exportdl-download" name="exportdl" value="1"' . $checked1 . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$radios = $fragment->parse('core/form/radio.php');

$formElements = [];
$n = [];
$n['label'] = rex_i18n::msg('backup_export_select_location');
$n['field'] = $radios;
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-form-exportfilename">' . rex_i18n::msg('backup_filename') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-exportfilename" name="exportfilename" value="' . rex_escape($exportfilename) . '" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="export" value="' . rex_i18n::msg('backup_db_export') . '">' . rex_i18n::msg('backup_to_export') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('backup_export'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" data-pjax="false" method="post">
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>

<script type="text/javascript" nonce="' . rex_response::getNonce() . '">
    <!--

    (function($) {
        var currentShown = null;
        $("#rex-js-exporttype-sql, #rex-js-exporttype-files").click(function(){
            if(currentShown) currentShown.hide();

            var effectParamsId = "#" + $(this).attr("id") + "-div";
            currentShown = $(effectParamsId);
            currentShown.fadeIn();
        }).filter(":checked").click();
    })(jQuery);

    //-->
</script>';

echo $content;
