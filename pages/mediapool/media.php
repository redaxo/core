<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($argFields) && is_string($argFields));
assert(isset($fileId) && is_int($fileId));

$subpage = Controller::getCurrentPagePart(2);

$mediaName = Request::request('media_name', 'string');
$csrf = CsrfToken::factory('mediapool');

// *************************************** KATEGORIEN CHECK UND AUSWAHL

$selMedia = new MediaCategorySelect($checkPerm = false);
$selMedia->setId('rex_file_category');
$selMedia->setName('rex_file_category');
$selMedia->setSize(1);
$selMedia->setSelected($rexFileCategory);
$selMedia->setAttribute('onchange', 'this.form.submit();');
$selMedia->setAttribute('class', 'selectpicker');
$selMedia->setAttribute('data-live-search', 'true');

if (Core::requireUser()->getComplexPerm('media')->hasAll()) {
    $selMedia->addOption(I18n::msg('pool_kats_no'), '0');
}

// ----- EXTENSION POINT
echo Extension::registerPoint(new ExtensionPoint('PAGE_MEDIAPOOL_HEADER', '', [
    'subpage' => $subpage,
    'category_id' => $rexFileCategory,
]));

$formElements = [];
$n = [];
$n['field'] = '<input class="form-control" style="border-left: 0;" type="text" name="media_name" id="be_search-media-name" value="' . escape($mediaName) . '" />';
$n['right'] = '<button class="btn btn-search" type="submit"><i class="rex-icon rex-icon-search"></i></button>';
$formElements[] = $n;
$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);

$formElements = [];
$n = [];
$n['before'] = $selMedia->get();
$n['after'] = '<search role="search">' . $fragment->parse('core/form/input_group.php') . '</search>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$toolbar = '<div class="rex-truncate-dropdown">' . $fragment->parse('core/form/input_group.php') . '</div>';

$toolbar = '
<div class="navbar-form navbar-right">
<form action="' . Url::currentBackendPage() . '" method="post">
    ' . $argFields . '
    <div class="form-group">
    ' . $toolbar . '
    </div>
</form>
</div>';

$context = new Context([
    'page' => Controller::getCurrentPage(),
]);

// ----- EXTENSION POINT
$toolbar = Extension::registerPoint(new ExtensionPoint('MEDIA_LIST_TOOLBAR', $toolbar, [
    'subpage' => $subpage,
    'category_id' => $rexFileCategory,
]));

// *************************************** Subpage: Media

if ($fileId) {
    require __DIR__ . '/media.detail.php';
}

// *************************************** SUBPAGE: "" -> MEDIEN ANZEIGEN

if (!$fileId) {
    require __DIR__ . '/media.list.php';
}
