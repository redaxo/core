<?php

assert(isset($csrf) && $csrf instanceof rex_csrf_token);
assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($openerInputField) && is_string($openerInputField));
assert(isset($argFields) && is_string($argFields));
assert(isset($toolbar) && is_string($toolbar));

// defaults for globals passed in from index.php
if (!isset($success)) {
    $success = '';
}
if (!isset($error)) {
    $error = '';
}
if (!isset($openerLink)) {
    $openerLink = '';
}
if (!isset($fileId)) {
    $fileId = 0;
}

$perm = rex::requireUser()->getComplexPerm('media');

if (rex_post('btn_delete', 'string')) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } else {
        $sql = rex_sql::factory()->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE id = ?', [$fileId]);
        $media = null;
        if (1 == $sql->getRows()) {
            $media = rex_media::get((string) $sql->getValue('filename'));
        }

        if ($media) {
            $filename = $media->getFileName();
            if ($perm->hasCategoryPerm($media->getCategoryId())) {
                try {
                    rex_media_service::deleteMedia($filename);
                    $success = rex_i18n::msg('pool_file_deleted');
                    $fileId = 0;

                    return;
                } catch (rex_api_exception $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = rex_i18n::msg('no_permission');
            }
        } else {
            $error = rex_i18n::msg('pool_file_not_found');
            $fileId = 0;
        }
    }
}

if (rex_post('btn_update', 'string')) {
    if (!$csrf->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
    } else {
        $gf = rex_sql::factory();
        $gf->setQuery('select * from ' . rex::getTablePrefix() . 'media where id=?', [$fileId]);
        if (1 != $gf->getRows()) {
            $error = rex_i18n::msg('pool_file_not_found');
            $fileId = 0;
        } elseif (!$perm->hasCategoryPerm($gf->getValue('category_id')) || !$perm->hasCategoryPerm($rexFileCategory)) {
            $error = rex_i18n::msg('no_permission');
        } else {
            $filename = (string) $gf->getValue('filename');
            $data = [];
            $data['category_id'] = $rexFileCategory;
            $data['title'] = rex_request('ftitle', 'string');

            if ($_FILES['file_new'] ?? null) {
                $data['file'] = rex_files('file_new', [
                    ['name', 'string'],
                    ['tmp_name', 'string'],
                    ['error', 'int'],
                ]);
            }

            try {
                rex_media_service::updateMedia($filename, $data);

                if ($gf->getValue('category_id') != $rexFileCategory) {
                    rex_extension::registerPoint(new rex_extension_point('MEDIA_MOVED', null, [
                        'filename' => $filename,
                        'category_id' => $rexFileCategory,
                    ]));
                }
                $success = rex_i18n::msg('pool_file_infos_updated');
            } catch (rex_api_exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$gf = rex_sql::factory();
$gf->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media WHERE id = ?', [$fileId]);
if (1 != $gf->getRows()) {
    $error = rex_i18n::msg('pool_file_not_found');
    $fileId = 0;

    return;
}

$TPERM = false;
if ($perm->hasCategoryPerm($gf->getValue('category_id'))) {
    $TPERM = true;
}

$ftitle = (string) $gf->getValue('title');
$fname = (string) $gf->getValue('filename');
$ffiletype = $gf->getValue('filetype');
$ffileSize = (int) $gf->getValue('filesize');
$ffileSize = rex_formatter::bytes($ffileSize);
$rexFileCategory = (int) $gf->getValue('category_id');

$sidebar = '';
$addExtInfo = '';
$encodedFname = urlencode($fname);

$isImage = rex_media::isImageType(rex_file::extension($fname));
if ($isImage) {
    $fwidth = (int) $gf->getValue('width');
    $fheight = (int) $gf->getValue('height');

    if ($fwidth > 199) {
        $rfwidth = 200;
    } else {
        $rfwidth = $fwidth;
    }

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_img_width') . ' / ' . rex_i18n::msg('pool_img_height') . '</label>';
    $e['field'] = '<p class="form-control-static">' . $fwidth . 'px / ' . $fheight . 'px</p>';

    $fragment = new rex_fragment();
    $fragment->setVar('elements', [$e], false);
    $addExtInfo = $fragment->parse('core/form/form.php');

    $imgn = rex_url::media($fname) . '?buster=' . $gf->getDateTimeValue('updatedate');
    $width = '';

    if ($rfwidth > 0) {
        $width = ' width="' . $rfwidth . '"';
    }
    $imgMax = rex_url::media($fname);

    if (rex_addon::get('media_manager')->isAvailable() && 'svg' != rex_file::extension($fname)) {
        $imgn = rex_media_manager::getUrl('rex_media_medium', $encodedFname, $gf->getDateTimeValue('updatedate'));
        $imgMax = rex_media_manager::getUrl('rex_media_large', $encodedFname, $gf->getDateTimeValue('updatedate'));

        $width = '';
    }

    if (!is_file(rex_path::media($fname))) {
        $sidebar = '<i class="rex-mime rex-mime-error"></i><span class="sr-only">' . $fname . '</span>';
    } else {
        $sidebar = '
                <a href="' . $imgMax . '">
                    <img class="img-responsive" src="' . $imgn . '"' . $width . ' alt="' . rex_escape($ftitle) . '" title="' . rex_escape($ftitle) . '" />
                </a>';
    }
}

if ('' != $error) {
    echo rex_view::error($error);
    $error = '';
}
if ('' != $success) {
    echo rex_view::success($success);
    $success = '';
}

if ('' != $openerInputField) {
    $openerLink = '<a class="btn btn-xs btn-select rex-js-media-select"
        data-id="' . $gf->getValue('id') . '" data-file_name="'.rex_escape($encodedFname).'" data-title="' . rex_escape($gf->getValue('title')) . '"
        data-select_type="' . (str_starts_with($openerInputField, 'REX_MEDIALIST_') ? "multiple" : "single") . '">' . rex_i18n::msg('pool_file_get') . '</a>';
}

if ('' != $openerLink) {
    $openerLink = ' | ' . $openerLink;
}

// ----- EXTENSION POINT
$sidebar = rex_extension::registerPoint(new rex_extension_point('MEDIA_DETAIL_SIDEBAR', $sidebar, [
    'id' => $fileId,
    'filename' => $fname,
    'media' => $gf,
    'is_image' => $isImage,
]));

if ($TPERM) {
    $panel = '';

    $catsSel = new rex_media_category_select();
    $catsSel->setStyle('class="form-control"');
    $catsSel->setSize(1);
    $catsSel->setName('rex_file_category');
    $catsSel->setId('rex-mediapool-category');
    $catsSel->setAttribute('class', 'selectpicker form-control');
    $catsSel->setAttribute('data-live-search', 'true');
    $catsSel->setSelected($rexFileCategory);

    if ($perm->hasAll()) {
        $catsSel->addOption(rex_i18n::msg('pool_kats_no'), '0');
    }

    $formElements = [];

    $e = [];
    $e['label'] = '<label for="rex-mediapool-title">' . rex_i18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . rex_escape($ftitle) . '" />';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label for="rex-mediapool-category">' . rex_i18n::msg('pool_file_category') . '</label>';
    $e['field'] = $catsSel->get();
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= rex_extension::registerPoint(new rex_extension_point('MEDIA_FORM_EDIT', '', ['id' => $fileId, 'media' => $gf]));

    $panel .= $addExtInfo;

    $formElements = [];

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_filename') . '</label>';
    $e['field'] = '<p class="form-control-static rex-word-break"><a href="' . rex_url::media($encodedFname) . '">' . rex_escape($fname) . '</a> <span class="rex-filesize">' . $ffileSize . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_last_update') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . rex_escape($gf->getValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . rex_escape($gf->getValue('createuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_file_exchange') . '</label>';
    $e['field'] = '<input type="file" name="file_new" />';
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $formElements = [];

    $e = [];
    $e['field'] = '<button class="btn btn-apply rex-form-aligned" type="submit" value="' . rex_i18n::msg('pool_file_update') . '" name="btn_update">' . rex_i18n::msg('pool_file_update') . '</button>';
    $formElements[] = $e;
    $e = [];
    $e['field'] = '<button class="btn btn-delete" type="submit" value="' . rex_i18n::msg('pool_file_delete') . '" name="btn_delete" data-confirm="' . rex_i18n::msg('delete') . ' ?">' . rex_i18n::msg('delete') . '</button>';
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    if ('' != $sidebar) {
        $fragment = new rex_fragment();
        $fragment->setVar('content', [$panel, $sidebar], false);
        $fragment->setVar('classes', ['col-sm-8', 'col-sm-4'], false);
        $panel = $fragment->parse('core/page/grid.php');
    }

    $body = '
        <form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data" data-pjax="false">
            ' . $csrf->getHiddenField() . '
            <input type="hidden" name="file_id" value="' . $fileId . '" />
            ' . $argFields . '
            ' . $panel . '
            ' . $buttons . '
        </form>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', rex_i18n::msg('pool_file_edit') . $openerLink, false);
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('body', $body, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} else {
    $panel = '';

    $catname = rex_i18n::msg('pool_kats_no');
    $Cat = rex_media_category::get($rexFileCategory);
    if ($Cat) {
        $catname = $Cat->getName();
    }

    $ftitle .= ' [' . $fileId . ']';
    $catname .= ' [' . $rexFileCategory . ']';

    $formElements = [];

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_escape($ftitle) . '</p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_file_category') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_escape($catname) . '</p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_filename') . '</label>';
    $e['field'] = '<p class="form-control-static"><a href="' . rex_url::media($encodedFname) . '">' . rex_escape($fname) . '</a>  <span class="rex-filesize">' . $ffileSize . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_last_update') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . rex_escape((string) $gf->getValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . rex_i18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . rex_formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . rex_escape((string) $gf->getValue('createuser')) . '</span></p>';
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    if ('' != $sidebar) {
        $fragment = new rex_fragment();
        $fragment->setVar('content', [$panel, $sidebar], false);
        $fragment->setVar('classes', ['col-sm-8', 'col-sm-4'], false);
        $panel = $fragment->parse('core/page/grid.php');
    }

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('pool_file_details') . $openerLink, false);
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('body', $panel, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
