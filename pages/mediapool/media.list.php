<?php

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPoolCache;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($openerInputField) && is_string($openerInputField));
assert(isset($argFields) && is_string($argFields));
assert(isset($toolbar) && is_string($toolbar));
assert(isset($rexFileCategoryName) && is_string($rexFileCategoryName));
/** @psalm-suppress RedundantCondition */
assert(isset($csrf) && $csrf instanceof CsrfToken);

// defaults for globals passed in from index.php
if (!isset($success)) {
    $success = '';
}
if (!isset($error)) {
    $error = '';
}

/** @var array{types?: string, opener_input_field?: string} $argUrl */
$argUrl ??= [];

$mediaMethod = Request::request('media_method', 'string');

$perm = Core::requireUser()->getComplexPerm('media');
$hasCategoryPerm = $perm->hasCategoryPerm($rexFileCategory);

if ($hasCategoryPerm && 'updatecat_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = Request::post('selectedmedia', 'array');
        if (isset($selectedmedia[0]) && '' != $selectedmedia[0]) {
            $error = [];
            $success = [];

            $countMoved = 0;
            foreach ($selectedmedia as $fileName) {
                $fileName = (string) $fileName;
                $media = Media::get($fileName);

                if (!$media) {
                    $error[] = I18n::msg('pool_file_not_found', $fileName);

                    continue;
                }

                // the target category is already covered by $hasCategoryPerm
                if (!$perm->hasCategoryPerm($media->categoryId)) {
                    $error[] = I18n::msg('no_rights_to_this_function');

                    continue;
                }

                $db = Sql::factory();
                // $db->setDebug();
                $db->setTable(Core::getTablePrefix() . 'media');
                $db->setWhere(['filename' => $fileName]);
                $db->setValue('category_id', $rexFileCategory);
                $db->addGlobalUpdateFields();
                $db->update();
                ++$countMoved;
                MediaPoolCache::delete($fileName);

                Extension::dispatch(new ExtensionPoint('MEDIA_MOVED', null, [
                    'filename' => $fileName,
                    'category_id' => $rexFileCategory,
                ]));
            }

            if ($countMoved) {
                $success[] = I18n::msg('pool_selectedmedia_moved');
            }
        } else {
            $error = I18n::msg('pool_selectedmedia_error');
        }
    }
}

if ($hasCategoryPerm && 'delete_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = Request::post('selectedmedia', 'array');
        if (0 != count($selectedmedia)) {
            $error = [];
            $success = [];

            $countDeleted = 0;
            foreach ($selectedmedia as $filename) {
                $media = Media::get($filename);
                if ($media) {
                    if ($perm->hasCategoryPerm($media->categoryId)) {
                        try {
                            MediaHandler::deleteMedia($filename);
                            ++$countDeleted;
                        } catch (ApiFunctionException $e) {
                            $error[] = $e->getMessage();
                        }
                    } else {
                        $error[] = I18n::msg('no_rights_to_this_function');
                    }
                } else {
                    $error[] = I18n::msg('pool_file_not_found', $filename);
                }
            }
            if ($countDeleted) {
                $success[] = I18n::msg('pool_files_deleted', $countDeleted);
            }
        } else {
            $error = I18n::msg('pool_selectedmedia_error');
        }
    }
}

$catsSel = new MediaCategorySelect();
$catsSel->setSize(1);
$catsSel->setStyle('class="form-control selectpicker"');
$catsSel->setAttribute('data-live-search', 'true');
$catsSel->setName('rex_file_category');
$catsSel->setId('rex_file_category');
$catsSel->setSelected($rexFileCategory);

if ($perm->hasAll()) {
    $catsSel->addOption(I18n::msg('pool_kats_no'), '0');
}

if ($error) {
    if (is_array($error)) {
        $error = implode('<br />', $error);
    }

    echo Message::error($error);
    $error = '';
}

if ($success) {
    if (is_array($success)) {
        $success = implode('<br />', $success);
    }

    echo Message::success($success);
    $success = '';
}

if (!empty($argUrl['types'])) {
    echo Message::info(I18n::msg('pool_file_filter') . ' <code>' . escape($argUrl['types']) . '</code>');
}

// Add Filter to the Query
$filter = [];

$mediaName = Request::request('media_name', 'string');
if ('' != $mediaName) {
    $filter['term'] = $mediaName;

    if (0 != $rexFileCategory) {
        $filter['category_id_path'] = $rexFileCategory;
    }
} else {
    $filter['category_id'] = $rexFileCategory;
}

if (isset($argUrl['types'])) {
    $filter['types'] = explode(',', $argUrl['types']);
}

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'rex_file_category' => $rexFileCategory,
    'media_name' => $mediaName,
    ...$argUrl,
]);
$pager = new Pager(Type::int(Core::getProperty('rows_per_page', 100)));

$items = MediaHandler::getList($filter, [], $pager);

$mediaFragment = new Fragment();
$mediaFragment->setVar('pager', $pager);
$mediaFragment->setVar('urlprovider', $context);

$panel = $mediaFragment->parse('core/navigations/pagination.php');

// deletefilelist und cat change
$panel .= '
<form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
    <fieldset>
        ' . $csrf->getHiddenField() . '
        <input type="hidden" id="media_method" name="media_method" value="" />
        ' . $argFields . '

        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::backendController(array_merge(['page' => 'mediapool/upload'], $argUrl)) . '"' . Core::getAccesskey(I18n::msg('pool_file_insert'), 'add') . ' title="' . I18n::msg('pool_file_insert') . '"><i class="rex-icon rex-icon-add-media"></i></a></th>
                <th class="rex-table-thumbnail">' . I18n::msg('pool_file_thumbnail') . '</th>
                <th>' . I18n::msg('pool_file_info') . ' / ' . I18n::msg('pool_file_description') . '</th>
                <th>' . I18n::msg('pool_last_update') . '</th>
                <th class="rex-table-action" colspan="2">' . I18n::msg('pool_file_functions') . '</th>
            </tr>
            </thead>';

// ----- move, delete and get selected items
if ($hasCategoryPerm) {
    $addInput = '';
    $filecat = Sql::factory();
    $filecat->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media_category ORDER BY name ASC LIMIT 1');

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_select_all') . '</label>';
    $e['field'] = '<input type="checkbox" name="checkie" value="0" onclick="setAllCheckBoxes(\'selectedmedia[]\',this)" />';
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $checkbox = $fragment->parse('core/form/checkbox.php');

    $field = '';
    if ($filecat->getRows() > 0) {
        $e = [];
        $e['field'] = $catsSel->get();
        $e['left'] = I18n::msg('pool_changecat_selectedmedia_prefix');
        $e['right'] = '<button class="btn btn-update" type="submit" onclick="var needle=new getObj(\'media_method\');needle.obj.value=\'updatecat_selectedmedia\';">' . I18n::msg('pool_changecat_selectedmedia_suffix') . '</button>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);
        $field .= '<div class="rex-truncate-dropdown">' . $fragment->parse('core/form/input_group.php') . '</div>';
    }

    $buttons = [];

    $button = [];
    $button['label'] = I18n::msg('pool_delete_selectedmedia');
    $button['attributes']['class'][] = 'btn-delete';
    $button['attributes']['type'][] = 'submit';
    $button['attributes']['onclick'][] = 'if(confirm(\'' . I18n::msg('delete') . ' ?\')){var needle=new getObj(\'media_method\');needle.obj.value=\'delete_selectedmedia\';}else{return false;}';
    $buttons[] = $button;

    // $buttons = '<button class="btn btn-delete" type="submit" onclick="if(confirm(\'' . I18n::msg('delete') . ' ?\')){var needle=new getObj(\'media_method\');needle.obj.value=\'delete_selectedmedia\';}else{return false;}">' . I18n::msg('pool_delete_selectedmedia') . '</button>';
    if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
        $button = [];
        $button['label'] = I18n::msg('pool_get_selectedmedia');
        $button['attributes']['class'][] = 'btn-apply';
        $button['attributes']['type'][] = 'submit';
        $button['attributes']['onclick'][] = 'selectMediaListArray(\'selectedmedia[]\');return false;';
        $buttons[] = $button;
    }

    $actionButtons = '';
    foreach ($buttons as $button) {
        $fragment = new Fragment();
        $fragment->setVar('buttons', [$button], false);
        $actionButtons .= $fragment->parse('core/buttons/button.php');
        $actionButtons .= ' ';
    }

    $field = '<div class="row"><div class="col-sm-7">' . $field . '</div><div class="col-sm-5 text-right">' . $actionButtons . '</div>';

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_selectedmedia') . '</label>';
    $e['field'] = $field;
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $field = $fragment->parse('core/form/form.php');

    $panel .= '
                <tfoot class="rex-sticky-table-footer">
                <tr>
                    <td colspan="2">
                        ' . $checkbox . '
                    </td>
                    <td colspan="4">
                        ' . $field . '
                    </td>
                </tr>
                </tfoot>
                ';
}

$panel .= '<tbody>';

foreach ($items as $media) {
    $alt = escape($media->title);
    $desc = '<p>' . escape(strip_tags((string) $media->getValue('med_description'))) . '</p>';

    if (!is_file(Path::media($media->fileName))) {
        $thumbnail = '<i class="rex-mime rex-mime-error" title="' . I18n::msg('pool_file_does_not_exist') . '"></i><span class="sr-only">' . $media->fileName . '</span>';
    } else {
        $fileExt = File::extension($media->fileName);
        $thumbnail = '<i class="rex-mime rex-mime-' . escape($fileExt) . '" title="' . $alt . '" data-extension="' . $fileExt . '"></i><span class="sr-only">' . $media->fileName . '</span>';

        if ('svg' === $fileExt) {
            // SVG is rendered by the browser directly, no image driver involved
            $thumbnail = '<img class="thumbnail" src="' . Url::media($media->fileName) . '?buster=' . $media->getValue('updatedate') . '" width="80" height="80" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
        } elseif (MediaManager::canPreview($fileExt)) {
            // a rasterised preview can be produced (image formats always, plus e.g. PDF when the driver supports it)
            $thumbnail = '<img class="thumbnail" src="' . MediaManager::getUrl('rex_media_small', urlencode($media->fileName), $media->getValue('updatedate')) . '" width="100" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
        }
    }

    // Register new EP MEDIA_LIST_THUMBNAIL - fuer Vorschau-Manipulation z.B. fuer Plyr/Lottie
    // ----- EXTENSION POINT
    $thumbnail = Extension::dispatch(new ExtensionPoint('MEDIA_LIST_THUMBNAIL', $thumbnail, [
        'id' => $media->id,
        'filename' => $media->fileName,
        'media' => $media,
    ]));

    if ('' == $media->title) {
        $fileTitle = '[' . I18n::msg('pool_file_notitle') . ']';
    }

    $openerLink = '';
    if ('' != $openerInputField) {
        $openerLink = '<a class="btn btn-xs btn-select" onclick="selectMedia(\'' . $media->fileName . '\', \'' . escape($media->title, 'js') . '\'); return false;">' . I18n::msg('pool_file_get') . '</a>';
        if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
            $openerLink = '<a class="btn btn-xs btn-select btn-highlight" onclick="selectMedialist(\'' . $media->fileName . '\', this);return false;">' . I18n::msg('pool_file_get') . '</a>';
        }
    }

    $ilink = Url::currentBackendPage(array_merge(['file_id' => $media->id, 'rex_file_category' => $rexFileCategory], $argUrl));

    $addTd = '<td></td>';
    // the search may list media from categories the user has no permission for
    if ($hasCategoryPerm && $perm->hasCategoryPerm($media->categoryId)) {
        $addTd = '<td><input type="checkbox" name="selectedmedia[]" value="' . $media->fileName . '" /></td>';
    }

    $panel .= '<tr>
                    ' . $addTd . '
                    <td class="rex-word-break" data-title="' . I18n::msg('pool_file_thumbnail') . '"><a href="' . $ilink . '">' . $thumbnail . '</a></td>
                    <td class="rex-word-break" data-title="' . I18n::msg('pool_file_info') . '">
                        <h3><a class="rex-link-expanded" href="' . $ilink . '">' . escape($media->title) . '</a></h3>
                        ' . $desc . '
                        <p>' . escape($media->fileName) . ' <span class="rex-filesize">' . Formatter::bytes($media->size) . '</span></p>
                    </td>
                    <td data-title="' . I18n::msg('pool_last_update') . '"><p class="rex-date">' . Formatter::intlDateTime($media->updateDate) . '</p><p class="rex-author">' . escape($media->updateUser) . '</p></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . $ilink . '">' . I18n::msg('edit') . '</a></td>
                    <td class="rex-table-action">';

    $panel .= Extension::dispatch(new ExtensionPoint('MEDIA_LIST_FUNCTIONS', $openerLink, [
        'media' => $media, // new
        'file_id' => $media->id, // @deprecated
        'file_name' => $media->fileName, // @deprecated
        'file_oname' => $media->originalFileName, // @deprecated
        'file_title' => $media->title, // @deprecated
        'file_type' => $media->type, // @deprecated
        'file_size' => $media->size, // @deprecated
        'file_stamp' => $media->updateDate, // @deprecated
        'file_updateuser' => $media->updateUser, // @deprecated
    ]));

    $panel .= '</td>
                </tr>';
}

// ----- no items found
if (0 == $pager->getRowCount()) {
    $panel .= '
            <tr>
                <td></td>
                <td colspan="5">' . I18n::msg('pool_nomediafound') . '</td>
            </tr>';
}

$panel .= '
                </tbody>
        </table>
    </fieldset>
</form>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('pool_file_caption', $rexFileCategoryName), false);
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $panel, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
