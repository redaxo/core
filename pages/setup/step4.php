<?php

use Redaxo\Core\Backup\Backup;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Setup\Importer;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

assert(isset($context) && $context instanceof Context);
assert(isset($errors) && is_array($errors));
assert(isset($cancelSetupBtn));

$tablesComplete = '' == Importer::verifyDbSchema();

$createdb = Request::post('createdb', 'int', '');

$headline = View::title(I18n::msg('setup_400') . $cancelSetupBtn);

$content = '
            <fieldset class="rex-js-setup-step-4">
            ';

$submitMessage = I18n::msg('setup_411');
if (count($errors) > 0) {
    $errors[] = Message::error(I18n::msg('setup_403'));
    $headline .= implode('', $errors);
    $submitMessage = I18n::msg('setup_412');
}

foreach (Setup::checkDbSecurity() as $message) {
    $headline .= Message::warning($message);
}

$dbchecked = array_fill(0, 6, '');
switch ($createdb) {
    case Setup::DB_MODE_SETUP_AND_OVERRIDE:
    case Setup::DB_MODE_SETUP_SKIP:
    case Setup::DB_MODE_SETUP_IMPORT_BACKUP:
    case Setup::DB_MODE_SETUP_UPDATE_FROM_PREVIOUS:
        $dbchecked[$createdb] = ' checked="checked"';
        break;
    default:
        if ($tablesComplete) {
            $dbchecked[Setup::DB_MODE_SETUP_SKIP] = ' checked="checked"';
        } else {
            $dbchecked[Setup::DB_MODE_SETUP_NO_OVERRIDE] = ' checked="checked"';
        }
}

// Vorhandene Exporte auslesen
$selExport = new Select();
$selExport->setName('import_name');
$selExport->setId('rex-form-import-name');
$selExport->setSize(1);
$selExport->setStyle('class="form-control selectpicker rex-js-import-name"');
$selExport->setAttribute('onclick', 'checkInput(\'createdb_3\')');
$exportDir = Backup::getDir();
$exportsFound = false;

if (is_dir($exportDir)) {
    $exportSqls = [];

    if ($handle = opendir($exportDir)) {
        while (false !== ($file = readdir($handle))) {
            if ('.' == $file || '..' == $file) {
                continue;
            }

            $file = preg_replace('/\.sql(?:\.gz)?$/', '', $file, -1, $count);
            if ($count) {
                $exportSqls[] = $file;
                $exportsFound = true;
            }
        }
        closedir($handle);
    }

    foreach ($exportSqls as $sqlExport) {
        $selExport->addOption($sqlExport, $sqlExport);
    }
}

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-form-createdb-0">' . I18n::msg('setup_404') . '</label>';
$n['field'] = '<input type="radio" id="rex-form-createdb-0" name="createdb" value="' . Setup::DB_MODE_SETUP_NO_OVERRIDE . '"' . $dbchecked[0] . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-createdb-1">' . I18n::msg('setup_405') . '</label>';
$n['field'] = '<input type="radio" id="rex-form-createdb-1" name="createdb" value="' . Setup::DB_MODE_SETUP_AND_OVERRIDE . '"' . $dbchecked[1] . ' />';
$n['note'] = I18n::msg('setup_405_note');
$formElements[] = $n;

if ($tablesComplete) {
    $n = [];
    $n['label'] = '<label for="rex-form-createdb-2">' . I18n::msg('setup_406') . '</label>';
    $n['field'] = '<input type="radio" id="rex-form-createdb-2" name="createdb" value="' . Setup::DB_MODE_SETUP_SKIP . '"' . $dbchecked[2] . ' />';
    $n['note'] = I18n::msg('setup_406_note');
    $formElements[] = $n;
}

$n = [];
$n['label'] = '<label for="rex-form-createdb-4">' . I18n::msg('setup_414') . '</label>';
$n['field'] = '<input type="radio" id="rex-form-createdb-4" name="createdb" value="' . Setup::DB_MODE_SETUP_UPDATE_FROM_PREVIOUS . '"' . $dbchecked[4] . ' />';
$n['note'] = I18n::msg('setup_414_note');
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$mode = $fragment->parse('core/form/radio.php');

if ($exportsFound) {
    $formElements = [];
    $n = [];
    $n['label'] = '<label for="rex-form-createdb-3">' . I18n::msg('setup_407') . '</label>';
    $n['field'] = '<input type="radio" id="rex-form-createdb-3" name="createdb" value="' . Setup::DB_MODE_SETUP_IMPORT_BACKUP . '"' . $dbchecked[3] . ' />';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $mode .= $fragment->parse('core/form/radio.php');

    $formElements = [];
    $n = [];
    $n['field'] = $selExport->get();
    $n['note'] = I18n::msg('backup_version_warning');
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $mode .= $fragment->parse('core/form/form.php');
}

$formElements = [];

$sql = Sql::factory();

$n = [];
$n['label'] = '<label>' . I18n::msg('version') . '</label>';
$n['field'] = '<p class="form-control-static">' . $sql->getDbType() . ' ' . $sql->getDbVersion() . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label class="required">' . I18n::msg('mode') . '</label>';
$n['field'] = $mode;
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-setup" type="submit" value="' . $submitMessage . '">' . $submitMessage . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$content .= '</form>';

$content .= '
            <script type="text/javascript" nonce="' . Response::getNonce() . '">
                 <!--
                jQuery(function($) {
                    var $container = $(".rex-js-setup-step-4");

                    // when opening backup dropdown -> mark corresponding radio button as checked
                    $container.find(".rex-js-import-name").click(function () {
                        $container.find("[name=createdb][value=' . Setup::DB_MODE_SETUP_IMPORT_BACKUP . ']").prop("checked", true);
                    });
                });
                 //-->
            </script>';

echo $headline;

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('setup_401'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '<form action="' . $context->getUrl(['step' => 5]) . '" method="post">' . $content . '</form>';
