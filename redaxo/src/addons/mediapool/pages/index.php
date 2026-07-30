<?php

global $ftitle, $error, $success;

// -------------- Defaults
$subpage = rex_be_controller::getCurrentPagePart(2);
$func = rex_request('func', 'string');
$success = rex_escape(rex_request('info', 'string'));
$error = rex_escape(rex_request('warning', 'string'));
$args = rex_request('args', 'array');

$regex = '@&lt;(/?(?:b|i|code)|br ?/?)&gt;@i';
$success = preg_replace($regex, '<$1>', $success);
$error = preg_replace($regex, '<$1>', $error);

// -------------- Additional Args
$argUrl = ['args' => $args];
$argFields = '';
foreach ($args as $argName => $argValue) {
    $argFields .= '<input type="hidden" name="args[' . rex_escape($argName) . ']" value="' . rex_escape($argValue) . '" />' . "\n";
}

// ----- opener_input_field setzen
$openerInputField = rex_request('opener_input_field', 'string', '');

if ('' != $openerInputField) {
    if (!preg_match('{^[A-Za-z]+[\w\-\:\.]*$}', $openerInputField)) {
        throw new Exception('invalid opener_input_field given: ' . $openerInputField);
    }

    $openerId = null;
    if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
        $openerId = (int) substr($openerInputField, 14, strlen($openerInputField));
    }

    $argUrl['opener_input_field'] = $openerInputField;
    $argFields .= '<input type="hidden" name="opener_input_field" value="' . rex_escape($openerInputField) . '"/>' . "\n";
}

// -------------- CatId in Session speichern
$fileId = rex_request('file_id', 'int');
$fileName = rex_request('file_name', 'string');
$rexFileCategory = rex_request('rex_file_category', 'int', -1);

if ('' != $fileName) {
    $sql = rex_sql::factory();
    $sql->setQuery('select * from ' . rex::getTablePrefix() . 'media where filename=?', [$fileName]);
    if (1 == $sql->getRows()) {
        $fileId = (int) $sql->getValue('id');
        $rexFileCategory = (int) $sql->getValue('category_id');
    }
}

if (-1 == $rexFileCategory) {
    $rexFileCategory = rex_session('media[rex_file_category]', 'int');
}

$gc = rex_sql::factory();
$gc->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media_category WHERE id=?', [$rexFileCategory]);
if (1 != $gc->getRows()) {
    $rexFileCategory = 0;
    $rexFileCategoryName = rex_i18n::msg('pool_kats_no');
} else {
    $rexFileCategoryName = $gc->getValue('name');
}

rex_set_session('media[rex_file_category]', $rexFileCategory);

// -------------- PERMS
$PERMALL = rex::requireUser()->getComplexPerm('media')->hasCategoryPerm(0);

// -------------- Header
$subline = rex_be_controller::getPageObject('mediapool')->getSubpages();

$argUrlString = rex_string::buildQuery($argUrl);
$argUrlString = $argUrlString ? '&' . $argUrlString : '';
foreach ($subline as $sp) {
    $sp->setHref($sp->getHref() . $argUrlString);
}

echo rex_view::title(rex_i18n::msg('pool_media'), $subline);

// -------------- Messages
if ('' != $success) {
    echo rex_view::info($success);
    $success = '';
}
if ('' != $error) {
    echo rex_view::error($error);
    $error = '';
}

if (!rex_request::isXmlHttpRequest()) {
    ?>
    <script type="text/javascript" nonce="<?= rex_response::getNonce() ?>">
        rex_retain_popup_event_handlers("rex:selectMedia");
        <?= $openerInputField ? 'rex.mediapoolOpenerInputField = "' . rex_escape($openerInputField, 'js') . '";' : '' ?>
    </script>
    <?php
}

// -------------- Include Page
rex_be_controller::includeCurrentPageSubPath(compact('openerInputField', 'argUrl', 'args', 'argFields', 'rexFileCategory', 'rexFileCategoryName', 'PERMALL', 'fileId', 'error', 'success'));
