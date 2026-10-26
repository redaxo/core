<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaListToolbar;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryChoices;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($argFields) && is_string($argFields));
assert(isset($fileId) && is_int($fileId));

/** @var array{types?: string, picker?: string, language?: int} $argUrl */
$argUrl ??= [];

$subpage = Controller::getCurrentPagePart(2);

$mediaName = Request::request('media_name', 'string');
$csrf = CsrfToken::factory('mediapool');

// *************************************** KATEGORIEN CHECK UND AUSWAHL

// all categories are offered for browsing, permissions only restrict editing
$categoryChoices = new MediaCategoryChoices(
    checkPerms: false,
    root: Core::requireUser()->getComplexPerm('media')->hasCategoryPerm(null),
);
$selMedia = new SelectInput(
    'rex_file_category',
    $categoryChoices->choices(),
    (string) $rexFileCategory,
    'rex_file_category',
    search: true,
    attributes: new HtmlAttributes(['onchange' => 'this.form.requestSubmit();']),
);

$formElements = [];
$n = [];
$n['field'] = '<input class="form-control" style="border-left: 0;" type="text" name="media_name" id="be_search-media-name" value="' . escape($mediaName) . '" />';
$n['right'] = '<button class="btn btn-search" type="submit"><i class="rex-icon rex-icon-search"></i></button>';
$formElements[] = $n;
$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);

$formElements = [];
$n = [];
$n['before'] = $selMedia->render();
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

// title and translatable meta fields are edited per language; the wrapper keeps the floated switch out of the
// section header that follows
$languageSwitch = View::languageSwitchAsButtons(new Context([
    'page' => Controller::getCurrentPage(),
    'rex_file_category' => $rexFileCategory,
    ...($fileId ? ['file_id' => $fileId] : []),
    ...$argUrl,
    'language' => Language::getCurrentId(),
]));
if ('' !== $languageSwitch) {
    echo '<div class="clearfix">' . $languageSwitch . '</div>';
}

$toolbar = Extension::dispatch(new MediaListToolbar($subpage, $rexFileCategory > 0 ? MediaCategory::get($rexFileCategory) : null, $toolbar));

// *************************************** Subpage: Media

if ($fileId) {
    require __DIR__ . '/media.detail.php';
}

// *************************************** SUBPAGE: "" -> MEDIEN ANZEIGEN

if (!$fileId) {
    require __DIR__ . '/media.list.php';
}
