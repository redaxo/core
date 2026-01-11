<?php

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($openerInputField) && is_string($openerInputField));
assert(isset($argFields) && is_string($argFields));
assert(isset($toolbar) && is_string($toolbar));
/** @psalm-suppress RedundantCondition */
assert(isset($csrf) && $csrf instanceof CsrfToken);

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

$perm = Core::requireUser()->getComplexPerm('media');

if (Request::post('btn_delete', 'string')) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $sql = Sql::factory()->setQuery('SELECT filename FROM ' . Core::getTable('media') . ' WHERE id = ?', [$fileId]);
        $media = null;
        if (1 == $sql->getRows()) {
            $media = Media::get((string) $sql->getValue('filename'));
        }

        if ($media) {
            $filename = $media->getFileName();
            if ($perm->hasCategoryPerm($media->getCategoryId())) {
                try {
                    MediaHandler::deleteMedia($filename);
                    $success = I18n::msg('pool_file_deleted');
                    $fileId = 0;

                    return;
                } catch (ApiFunctionException $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = I18n::msg('no_permission');
            }
        } else {
            $error = I18n::msg('pool_file_not_found');
            $fileId = 0;
        }
    }
}

if (Request::post('btn_update', 'string')) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $gf = Sql::factory();
        $gf->setQuery('select * from ' . Core::getTablePrefix() . 'media where id=?', [$fileId]);
        if (1 != $gf->getRows()) {
            $error = I18n::msg('pool_file_not_found');
            $fileId = 0;
        } elseif (!$perm->hasCategoryPerm($gf->getValue('category_id')) || !$perm->hasCategoryPerm($rexFileCategory)) {
            $error = I18n::msg('no_permission');
        } else {
            $filename = (string) $gf->getValue('filename');
            $data = [];
            $data['category_id'] = $rexFileCategory;
            $data['title'] = Request::request('ftitle', 'string');

            if ($_FILES['file_new'] ?? null) {
                $data['file'] = Request::files('file_new', [
                    ['name', 'string'],
                    ['tmp_name', 'string'],
                    ['error', 'int'],
                ]);
            }

            try {
                MediaHandler::updateMedia($filename, $data);

                if ($gf->getValue('category_id') != $rexFileCategory) {
                    Extension::registerPoint(new ExtensionPoint('MEDIA_MOVED', null, [
                        'filename' => $filename,
                        'category_id' => $rexFileCategory,
                    ]));
                }
                $success = I18n::msg('pool_file_infos_updated');
            } catch (ApiFunctionException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$gf = Sql::factory();
$gf->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media WHERE id = ?', [$fileId]);
if (1 != $gf->getRows()) {
    $error = I18n::msg('pool_file_not_found');
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
$ffileSize = Formatter::bytes($ffileSize);
$rexFileCategory = (int) $gf->getValue('category_id');

$sidebar = '';
$addExtInfo = '';
$encodedFname = urlencode($fname);

$isImage = Media::isImageType(File::extension($fname));
if ($isImage) {
    $fwidth = (int) $gf->getValue('width');
    $fheight = (int) $gf->getValue('height');

    if ($fwidth > 199) {
        $rfwidth = 200;
    } else {
        $rfwidth = $fwidth;
    }

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_img_width') . ' / ' . I18n::msg('pool_img_height') . '</label>';
    $e['field'] = '<p class="form-control-static">' . $fwidth . 'px / ' . $fheight . 'px</p>';

    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $addExtInfo = $fragment->parse('core/form/form.php');

    $imgn = Url::media($fname) . '?buster=' . $gf->getDateTimeValue('updatedate');
    $width = '';

    if ($rfwidth > 0) {
        $width = ' width="' . $rfwidth . '"';
    }
    $imgMax = Url::media($fname);

    if ('svg' != File::extension($fname)) {
        $imgn = MediaManager::getUrl('rex_media_medium', $encodedFname, $gf->getDateTimeValue('updatedate'));
        $imgMax = MediaManager::getUrl('rex_media_large', $encodedFname, $gf->getDateTimeValue('updatedate'));

        $width = '';
    }

    if (!is_file(Path::media($fname))) {
        $sidebar = '<i class="rex-mime rex-mime-error"></i><span class="sr-only">' . $fname . '</span>';
    } else {
        $sidebar = '
                <a href="' . $imgMax . '">
                    <img class="img-responsive" src="' . $imgn . '"' . $width . ' alt="' . escape($ftitle) . '" title="' . escape($ftitle) . '" />
                </a>';
    }
}

if ('' != $error) {
    echo Message::error($error);
    $error = '';
}
if ('' != $success) {
    echo Message::success($success);
    $success = '';
}

if ('' != $openerInputField) {
    $openerLink = '<a class="btn btn-xs btn-select" onclick="selectMedia(\'' . $encodedFname . '\', \'' . escape($gf->getValue('title'), 'js') . '\'); return false;">' . I18n::msg('pool_file_get') . '</a>';
    if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
        $openerLink = '<a class="btn btn-xs btn-select btn-highlight" onclick="selectMedialist(\'' . $encodedFname . '\'); return false;">' . I18n::msg('pool_file_get') . '</a>';
    }
}

if ('' != $openerLink) {
    $openerLink = ' | ' . $openerLink;
}

// ----- EXTENSION POINT
$sidebar = Extension::registerPoint(new ExtensionPoint('MEDIA_DETAIL_SIDEBAR', $sidebar, [
    'id' => $fileId,
    'filename' => $fname,
    'media' => $gf,
    'is_image' => $isImage,
]));

if ($TPERM) {
    $panel = '';

    $catsSel = new MediaCategorySelect();
    $catsSel->setStyle('class="form-control"');
    $catsSel->setSize(1);
    $catsSel->setName('rex_file_category');
    $catsSel->setId('rex-mediapool-category');
    $catsSel->setAttribute('class', 'selectpicker form-control');
    $catsSel->setAttribute('data-live-search', 'true');
    $catsSel->setSelected($rexFileCategory);

    if ($perm->hasAll()) {
        $catsSel->addOption(I18n::msg('pool_kats_no'), '0');
    }

    $formElements = [];

    $e = [];
    $e['label'] = '<label for="rex-mediapool-title">' . I18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . escape($ftitle) . '" />';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label for="rex-mediapool-category">' . I18n::msg('pool_file_category') . '</label>';
    $e['field'] = $catsSel->get();
    $formElements[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= Extension::registerPoint(new ExtensionPoint('MEDIA_FORM_EDIT', '', ['id' => $fileId, 'media' => $gf]));

    $panel .= $addExtInfo;

    $formElements = [];

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_filename') . '</label>';
    $e['field'] = '<p class="form-control-static rex-word-break"><a href="' . Url::media($encodedFname) . '">' . escape($fname) . '</a> <span class="rex-filesize">' . $ffileSize . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_last_update') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . escape($gf->getValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . escape($gf->getValue('createuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_file_exchange') . '</label>';
    $e['field'] = '<input type="file" name="file_new" />';
    $formElements[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $formElements = [];

    $e = [];
    $e['field'] = '<button class="btn btn-apply rex-form-aligned" type="submit" value="' . I18n::msg('pool_file_update') . '" name="btn_update">' . I18n::msg('pool_file_update') . '</button>';
    $formElements[] = $e;
    $e = [];
    $e['field'] = '<button class="btn btn-delete" type="submit" value="' . I18n::msg('pool_file_delete') . '" name="btn_delete" data-confirm="' . I18n::msg('delete') . ' ?">' . I18n::msg('delete') . '</button>';
    $formElements[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    if ('' != $sidebar) {
        $fragment = new Fragment();
        $fragment->setVar('content', [$panel, $sidebar], false);
        $fragment->setVar('classes', ['col-sm-8', 'col-sm-4'], false);
        $panel = $fragment->parse('core/page/grid.php');
    }

    $body = '
        <form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data" data-pjax="false">
            ' . $csrf->getHiddenField() . '
            <input type="hidden" name="file_id" value="' . $fileId . '" />
            ' . $argFields . '
            ' . $panel . '
            ' . $buttons . '
        </form>';

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', I18n::msg('pool_file_edit') . $openerLink, false);
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('body', $body, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} else {
    $panel = '';

    $catname = I18n::msg('pool_kats_no');
    $Cat = MediaCategory::get($rexFileCategory);
    if ($Cat) {
        $catname = $Cat->getName();
    }

    $ftitle .= ' [' . $fileId . ']';
    $catname .= ' [' . $rexFileCategory . ']';

    $formElements = [];

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<p class="form-control-static">' . escape($ftitle) . '</p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_file_category') . '</label>';
    $e['field'] = '<p class="form-control-static">' . escape($catname) . '</p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_filename') . '</label>';
    $e['field'] = '<p class="form-control-static"><a href="' . Url::media($encodedFname) . '">' . escape($fname) . '</a>  <span class="rex-filesize">' . $ffileSize . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_last_update') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . escape((string) $gf->getValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . escape((string) $gf->getValue('createuser')) . '</span></p>';
    $formElements[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    if ('' != $sidebar) {
        $fragment = new Fragment();
        $fragment->setVar('content', [$panel, $sidebar], false);
        $fragment->setVar('classes', ['col-sm-8', 'col-sm-4'], false);
        $panel = $fragment->parse('core/page/grid.php');
    }

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('pool_file_details') . $openerLink, false);
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('body', $panel, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
