<?php

assert(isset($csrf) && $csrf instanceof rex_csrf_token);
assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($openerInputField) && is_string($openerInputField));
assert(isset($argFields) && is_string($argFields));
assert(isset($toolbar) && is_string($toolbar));
assert(isset($rexFileCategoryName) && is_string($rexFileCategoryName));

// defaults for globals passed in from index.php
if (!isset($success)) {
    $success = '';
}
if (!isset($error)) {
    $error = '';
}

/** @var array{args?: array{types: string}, opener_input_field?: string} $argUrl */
$argUrl ??= [];

$mediaMethod = rex_request('media_method', 'string');

$perm = rex::requireUser()->getComplexPerm('media');
$hasCategoryPerm = $perm->hasCategoryPerm($rexFileCategory);

if ($hasCategoryPerm && 'updatecat_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = rex_post('selectedmedia', 'array');
        if (isset($selectedmedia[0]) && '' != $selectedmedia[0]) {
            foreach ($selectedmedia as $fileName) {
                $db = rex_sql::factory();
                // $db->setDebug();
                $db->setTable(rex::getTablePrefix() . 'media');
                $db->setWhere(['filename' => $fileName]);
                $db->setValue('category_id', $rexFileCategory);
                $db->addGlobalUpdateFields();
                try {
                    $db->update();
                    $success = rex_i18n::msg('pool_selectedmedia_moved');
                    rex_media_cache::delete($fileName);

                    rex_extension::registerPoint(new rex_extension_point('MEDIA_MOVED', null, [
                        'filename' => $fileName,
                        'category_id' => $rexFileCategory,
                    ]));
                } catch (rex_sql_exception) {
                    $error = rex_i18n::msg('pool_selectedmedia_error');
                }
            }
        } else {
            $error = rex_i18n::msg('pool_selectedmedia_error');
        }
    }
}

if ($hasCategoryPerm && 'delete_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = rex_post('selectedmedia', 'array');
        if (0 != count($selectedmedia)) {
            $error = [];
            $success = [];

            $countDeleted = 0;
            foreach ($selectedmedia as $filename) {
                $media = rex_media::get($filename);
                if ($media) {
                    if ($perm->hasCategoryPerm($media->getCategoryId())) {
                        try {
                            rex_media_service::deleteMedia($filename);
                            ++$countDeleted;
                        } catch (rex_api_exception $e) {
                            $error[] = $e->getMessage();
                        }
                    } else {
                        $error[] = rex_i18n::msg('no_permission');
                    }
                } else {
                    $error[] = rex_i18n::msg('pool_file_not_found', $filename);
                }
            }
            if ($countDeleted) {
                $success[] = rex_i18n::msg('pool_files_deleted', $countDeleted);
            }
        } else {
            $error = rex_i18n::msg('pool_selectedmedia_error');
        }
    }
}

$catsSel = new rex_media_category_select();
$catsSel->setSize(1);
$catsSel->setStyle('class="form-control selectpicker"');
$catsSel->setAttribute('data-live-search', 'true');
$catsSel->setName('rex_file_category');
$catsSel->setId('rex_file_category');
$catsSel->setSelected($rexFileCategory);

if ($perm->hasAll()) {
    $catsSel->addOption(rex_i18n::msg('pool_kats_no'), '0');
}

if ($error) {
    if (is_array($error)) {
        $error = implode('<br />', $error);
    }

    echo rex_view::error($error);
    $error = '';
}

if ($success) {
    if (is_array($success)) {
        $success = implode('<br />', $success);
    }

    echo rex_view::success($success);
    $success = '';
}

if (!empty($argUrl['args']['types'])) {
    echo rex_view::info(rex_i18n::msg('pool_file_filter') . ' <code>' . rex_escape($argUrl['args']['types']) . '</code>');
}

$addon = rex_addon::require('mediapool');

// Add Filter to the Query
$filter = [];

$mediaName = rex_request('media_name', 'string');
if ('' != $mediaName) {
    $filter['term'] = $mediaName;

    if ('global' != $addon->getConfig('searchmode', 'local') && 0 != $rexFileCategory) {
        $filter['category_id_path'] = $rexFileCategory;
    }
} else {
    $filter['category_id'] = $rexFileCategory;
}

if (isset($argUrl['args']['types']) && is_string($argUrl['args']['types'])) {
    $types = explode(',', $argUrl['args']['types']);
    $filter['types'] = $types;
}

$context = new rex_context([
    'page' => rex_be_controller::getCurrentPage(),
    'rex_file_category' => $rexFileCategory,
    'media_name' => $mediaName,
    ...$argUrl,
]);
$pager = new rex_pager(rex_type::int($addon->getProperty('rows_per_page', 100)));

$items = rex_media_service::getList($filter, [], $pager);

$mediaFragment = new rex_fragment();
$mediaFragment->setVar('pager', $pager);
$mediaFragment->setVar('urlprovider', $context);

$panel = $mediaFragment->parse('core/navigations/pagination.php');

// deletefilelist und cat change
$panel .= '
<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
    <fieldset>
        ' . $csrf->getHiddenField() . '
        <input type="hidden" id="media_method" name="media_method" value="" />
        ' . $argFields . '

        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th class="rex-table-icon"><a class="rex-link-expanded" href="' . rex_url::backendController(array_merge(['page' => 'mediapool/upload'], $argUrl)) . '"' . rex::getAccesskey(rex_i18n::msg('pool_file_insert'), 'add') . ' title="' . rex_i18n::msg('pool_file_insert') . '"><i class="rex-icon rex-icon-add-media"></i></a></th>
                <th class="rex-table-thumbnail">' . rex_i18n::msg('pool_file_thumbnail') . '</th>
                <th>' . rex_i18n::msg('pool_file_info') . ' / ' . rex_i18n::msg('pool_file_description') . '</th>
                <th>' . rex_i18n::msg('pool_last_update') . '</th>
                <th class="rex-table-action" colspan="2">' . rex_i18n::msg('pool_file_functions') . '</th>
            </tr>
            </thead>';

// ----- move, delete and get selected items
if ($hasCategoryPerm) {
    $addInput = '';
    $filecat = rex_sql::factory();
    $filecat->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media_category ORDER BY name ASC LIMIT 1');

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_select_all') . '</label>';
    $e['field'] = '<input type="checkbox" name="checkie" value="0" onclick="setAllCheckBoxes(\'selectedmedia[]\',this)" />';
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new rex_fragment();
    $fragment->setVar('elements', [$e], false);
    $checkbox = $fragment->parse('core/form/checkbox.php');

    $field = '';
    if ($filecat->getRows() > 0) {
        $e = [];
        $e['field'] = $catsSel->get();
        $e['left'] = rex_i18n::msg('pool_changecat_selectedmedia_prefix');
        $e['right'] = '<button class="btn btn-update" type="submit" onclick="var needle=new getObj(\'media_method\');needle.obj.value=\'updatecat_selectedmedia\';">' . rex_i18n::msg('pool_changecat_selectedmedia_suffix') . '</button>';

        $fragment = new rex_fragment();
        $fragment->setVar('elements', [$e], false);
        $field .= '<div class="rex-truncate-dropdown">' . $fragment->parse('core/form/input_group.php') . '</div>';
    }

    $buttons = [];

    $button = [];
    $button['label'] = rex_i18n::msg('pool_delete_selectedmedia');
    $button['attributes']['class'][] = 'btn-delete';
    $button['attributes']['type'][] = 'submit';
    $button['attributes']['onclick'][] = 'if(confirm(\'' . rex_i18n::msg('delete') . ' ?\')){var needle=new getObj(\'media_method\');needle.obj.value=\'delete_selectedmedia\';}else{return false;}';
    $buttons[] = $button;

    // $buttons = '<button class="btn btn-delete" type="submit" onclick="if(confirm(\'' . rex_i18n::msg('delete') . ' ?\')){var needle=new getObj(\'media_method\');needle.obj.value=\'delete_selectedmedia\';}else{return false;}">' . rex_i18n::msg('pool_delete_selectedmedia') . '</button>';
    if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
        $button = [];
        $button['label'] = rex_i18n::msg('pool_get_selectedmedia');
        $button['attributes']['class'][] = 'btn-apply';
        $button['attributes']['type'][] = 'submit';
        $button['attributes']['onclick'][] = 'selectMediaListArray(\'selectedmedia[]\');return false;';
        $buttons[] = $button;
    }

    $actionButtons = '';
    foreach ($buttons as $button) {
        $fragment = new rex_fragment();
        $fragment->setVar('buttons', [$button], false);
        $actionButtons .= $fragment->parse('core/buttons/button.php');
        $actionButtons .= ' ';
    }

    $field = '<div class="row"><div class="col-sm-7">' . $field . '</div><div class="col-sm-5 text-right">' . $actionButtons . '</div>';

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_selectedmedia') . '</label>';
    $e['field'] = $field;
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new rex_fragment();
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

if (!rex_addon::get('media_manager')->isAvailable()) {
    $mediaManagerUrl = null;
} else {
    $mediaManagerUrl = [rex_media_manager::class, 'getUrl'];
}

$panel .= '<tbody>';

foreach ($items as $media) {
    $alt = rex_escape($media->getTitle());
    $desc = '<p>' . rex_escape(strip_tags((string) $media->getValue('med_description'))) . '</p>';

    if (!is_file(rex_path::media($media->getFileName()))) {
        $thumbnail = '<i class="rex-mime rex-mime-error" title="' . rex_i18n::msg('pool_file_does_not_exist') . '"></i><span class="sr-only">' . $media->getFileName() . '</span>';
    } else {
        $fileExt = rex_file::extension($media->getFileName());
        $iconClass = ' rex-mime-default';
        if (rex_media::isDocType($fileExt)) {
            $iconClass = ' rex-mime-' . $fileExt;
        }
        $thumbnail = '<i class="rex-mime' . $iconClass . '" title="' . $alt . '" data-extension="' . $fileExt . '"></i><span class="sr-only">' . $media->getFileName() . '</span>';

        if (rex_media::isImageType(rex_file::extension($media->getFileName()))) {
            $thumbnail = '<img class="thumbnail" src="' . rex_url::media($media->getFileName()) . '?buster=' . $media->getValue('updatedate') . '" width="80" height="80" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
            if ($mediaManagerUrl && 'svg' != rex_file::extension($media->getFileName())) {
                $thumbnail = '<img class="thumbnail" src="' . $mediaManagerUrl('rex_media_small', urlencode($media->getFileName()), $media->getValue('updatedate')) . '" width="100" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
            }
        }
    }

    // Register new EP MEDIA_LIST_THUMBNAIL - fuer Vorschau-Manipulation z.B. fuer Plyr/Lottie
    // ----- EXTENSION POINT
    $thumbnail = rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_THUMBNAIL', $thumbnail, [
        'id' => $media->getId(),
        'filename' => $media->getFileName(),
        'media' => $media,
    ]));

    if ('' == $media->getTitle()) {
        $fileTitle = '[' . rex_i18n::msg('pool_file_notitle') . ']';
    }

    $openerLink = '';
    if ('' != $openerInputField) {
        $openerLink = '<a class="btn btn-xs btn-select" onclick="selectMedia(\'' . $media->getFileName() . '\', \'' . rex_escape($media->getTitle(), 'js') . '\'); return false;">' . rex_i18n::msg('pool_file_get') . '</a>';
        if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
            $openerLink = '<a class="btn btn-xs btn-select btn-highlight" onclick="selectMedialist(\'' . $media->getFileName() . '\', this);return false;">' . rex_i18n::msg('pool_file_get') . '</a>';
        }
    }

    $ilink = rex_url::currentBackendPage(array_merge(['file_id' => $media->getId(), 'rex_file_category' => $rexFileCategory], $argUrl));

    $addTd = '<td></td>';
    if ($hasCategoryPerm) {
        $addTd = '<td><input type="checkbox" name="selectedmedia[]" value="' . $media->getFileName() . '" /></td>';
    }

    $panel .= '<tr>
                    ' . $addTd . '
                    <td class="rex-word-break" data-title="' . rex_i18n::msg('pool_file_thumbnail') . '"><a href="' . $ilink . '">' . $thumbnail . '</a></td>
                    <td class="rex-word-break" data-title="' . rex_i18n::msg('pool_file_info') . '">
                        <h3><a class="rex-link-expanded" href="' . $ilink . '">' . rex_escape($media->getTitle()) . '</a></h3>
                        ' . $desc . '
                        <p>' . rex_escape($media->getFileName()) . ' <span class="rex-filesize">' . rex_formatter::bytes($media->getSize()) . '</span></p>
                    </td>
                    <td data-title="' . rex_i18n::msg('pool_last_update') . '"><p class="rex-date">' . rex_formatter::intlDateTime($media->getUpdateDate()) . '</p><p class="rex-author">' . rex_escape($media->getUpdateUser()) . '</p></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . $ilink . '">' . rex_i18n::msg('edit') . '</a></td>
                    <td class="rex-table-action">';

    $panel .= rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_FUNCTIONS', $openerLink, [
        'media' => $media, // new
        'file_id' => $media->getId(), // @deprecated
        'file_name' => $media->getFileName(), // @deprecated
        'file_oname' => $media->getOriginalFileName(), // @deprecated
        'file_title' => $media->getTitle(), // @deprecated
        'file_type' => $media->getType(), // @deprecated
        'file_size' => $media->getSize(), // @deprecated
        'file_stamp' => $media->getUpdateDate(), // @deprecated
        'file_updateuser' => $media->getUpdateUser(), // @deprecated
    ]));

    $panel .= '</td>
                </tr>';
}

// ----- no items found
if (0 == $pager->getRowCount()) {
    $panel .= '
            <tr>
                <td></td>
                <td colspan="5">' . rex_i18n::msg('pool_nomediafound') . '</td>
            </tr>';
}

$panel .= '
                </tbody>
        </table>
    </fieldset>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('pool_file_caption', $rexFileCategoryName), false);
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $panel, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
