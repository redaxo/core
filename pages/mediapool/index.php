<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

global $ftitle, $error, $success;

// -------------- Defaults
$subpage = Controller::getCurrentPagePart(2);
$func = Request::request('func', 'string');
$success = escape(Request::request('info', 'string'));
$error = escape(Request::request('warning', 'string'));
$types = Request::request('types', 'string');

$regex = '@&lt;(/?(?:b|i|code)|br ?/?)&gt;@i';
$success = preg_replace($regex, '<$1>', $success);
$error = preg_replace($regex, '<$1>', $error);

// -------------- Additional Args
$argUrl = [];
$argFields = '';
if ('' !== $types) {
    $argUrl['types'] = $types;
    $argFields .= '<input type="hidden" name="types" value="' . escape($types) . '" />' . "\n";
}

// ----- opener_input_field setzen
$openerInputField = Request::request('opener_input_field', 'string', '');

if ('' != $openerInputField) {
    if (!preg_match('{^[A-Za-z]+[\w\-\:\.]*$}', $openerInputField)) {
        throw new Exception('invalid opener_input_field given: ' . $openerInputField);
    }

    $openerId = null;
    if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
        $openerId = (int) substr($openerInputField, 14, strlen($openerInputField));
    }

    $argUrl['opener_input_field'] = $openerInputField;
    $argFields .= '<input type="hidden" name="opener_input_field" value="' . escape($openerInputField) . '"/>' . "\n";
}

// -------------- CatId in Session speichern
$fileId = Request::request('file_id', 'int');
$fileName = Request::request('file_name', 'string');
$rexFileCategory = Request::request('rex_file_category', 'int', -1);

if ('' != $fileName) {
    $sql = Sql::factory();
    $sql->setQuery('select * from ' . Core::getTablePrefix() . 'media where filename=?', [$fileName]);
    if (1 == $sql->getRows()) {
        $fileId = (int) $sql->getValue('id');
        $rexFileCategory = (int) $sql->getValue('category_id');
    }
}

$session = Session::start();

if (-1 == $rexFileCategory) {
    $rexFileCategory = (int) $session->get('media[rex_file_category]', 0);
}

$gc = Sql::factory();
$gc->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media_category WHERE id=?', [$rexFileCategory]);
if (1 != $gc->getRows()) {
    $rexFileCategory = 0;
    $rexFileCategoryName = I18n::msg('pool_kats_no');
} else {
    $rexFileCategoryName = $gc->getValue('name');
}

$session->set('media[rex_file_category]', $rexFileCategory);

// -------------- PERMS
$PERMALL = Core::requireUser()->getComplexPerm('media')->hasCategoryPerm(0);

// -------------- Header
$subline = Controller::getPageObject('mediapool')->getSubpages();

$argUrlString = Str::buildQuery($argUrl);
$argUrlString = $argUrlString ? '&' . $argUrlString : '';
foreach ($subline as $sp) {
    $sp->setHref($sp->getHref() . $argUrlString);
}

echo View::title(I18n::msg('pool_media'), $subline);

// -------------- Messages
if ('' != $success) {
    echo Message::info($success);
    $success = '';
}
if ('' != $error) {
    echo Message::error($error);
    $error = '';
}

if (!Request::isXmlHttpRequest()) {
    ?>
    <script type="text/javascript" nonce="<?= Response::getNonce() ?>">
        rex_retain_popup_event_handlers("rex:selectMedia");
        <?= $openerInputField ? 'rex.mediapoolOpenerInputField = "' . escape($openerInputField, 'js') . '";' : '' ?>
    </script>
    <?php
}

// -------------- Include Page
Controller::includeCurrentPageSubPath(compact('openerInputField', 'argUrl', 'argFields', 'rexFileCategory', 'rexFileCategoryName', 'PERMALL', 'fileId', 'error', 'success'));
