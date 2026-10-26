<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaDetailSidebar;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaFormEdit;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryChoices;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($picker) && is_string($picker));
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
if (!isset($fileId)) {
    $fileId = 0;
}

$perm = Core::requireUser()->getComplexPerm('media');

if (Request::post('btn_delete', 'string')) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $sql = Sql::factory()->setQuery('SELECT filename FROM rex_media WHERE id = ?', [$fileId]);
        $media = null;
        if (1 == $sql->getRows()) {
            $media = Media::get($sql->getStringValue('filename'));
        }

        if ($media) {
            $filename = $media->fileName;
            if ($perm->hasCategoryPerm($media->categoryId)) {
                try {
                    MediaHandler::delete($filename);
                    $success = I18n::msg('pool_file_deleted');
                    $fileId = 0;

                    return;
                } catch (UserMessageException $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = I18n::msg('no_rights_to_this_function');
            }
        } else {
            $error = I18n::msg('pool_file_not_found');
            $fileId = 0;
        }
    }
}

$meta = null;

if (Request::post('btn_update', 'string')) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $gf = Sql::factory();
        $gf->setQuery('SELECT * FROM rex_media WHERE id = ?', [$fileId]);
        if (1 != $gf->getRows()) {
            $error = I18n::msg('pool_file_not_found');
            $fileId = 0;
        } elseif (!$perm->hasCategoryPerm($gf->getNullableIntValue('category_id')) || !$perm->hasCategoryPerm($rexFileCategory)) {
            $error = I18n::msg('no_rights_to_this_function');
        } else {
            $filename = $gf->getStringValue('filename');
            $data = [];
            $data['category_id'] = $rexFileCategory ?: null;
            $data['title'] = Request::request('ftitle', 'string');

            /** @var mixed $file */
            $file = Core::getRequest()->files->get('file_new');
            if ($file instanceof UploadedFile) {
                $data['file'] = $file;
            }

            $meta = new MetaForm(new MetaContext(MetaEntity::Media, Media::get($filename), mediaCategory: $rexFileCategory > 0 ? MediaCategory::get($rexFileCategory) : null));

            try {
                if (!$meta->submit()) {
                    throw new UserMessageException(I18n::msg('minfo_metadata_invalid'));
                }

                MediaHandler::edit($filename, Language::getCurrentId(), $data, $meta->values());
                $success = I18n::msg('pool_file_infos_updated');
            } catch (UserMessageException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// the shared columns together with the translation of the current language
$gf = Sql::factory();
$gf->setQuery('SELECT m.*, l.* FROM rex_media m JOIN rex_media_translation l ON l.media_id = m.id WHERE m.id = ? AND l.language_id = ?', [(int) $fileId, Language::getCurrentId()]);
if (1 != $gf->getRows()) {
    $error = I18n::msg('pool_file_not_found');
    $fileId = 0;

    return;
}

$TPERM = false;
if ($perm->hasCategoryPerm($gf->getNullableIntValue('category_id'))) {
    $TPERM = true;
}

// a failed update shows the submitted title again, like the submitted meta values
$ftitle = $meta?->hasErrors() ? Request::request('ftitle', 'string') : $gf->getStringValue('title');
$fname = $gf->getStringValue('filename');
$media = Media::get($fname) ?? throw new LogicException(sprintf('Media "%s" does not exist.', $fname));
$ffiletype = $gf->getNullableStringValue('filetype');
$ffileSize = $gf->getIntValue('filesize');
$ffileSize = Formatter::bytes($ffileSize);
$rexFileCategory = $gf->getNullableIntValue('category_id') ?? 0;

$sidebar = '';
$addExtInfo = '';
$encodedFname = urlencode($fname);

$fileExt = File::extension($fname);
$isSvg = 'svg' === $fileExt;

$fwidth = $gf->getNullableIntValue('width') ?? 0;
$fheight = $gf->getNullableIntValue('height') ?? 0;

if ($fwidth > 0 && $fheight > 0) {
    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_img_width') . ' / ' . I18n::msg('pool_img_height') . '</label>';
    $e['field'] = '<p class="form-control-static">' . $fwidth . 'px / ' . $fheight . 'px</p>';

    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $addExtInfo = $fragment->parse('core/form/form.php');
}

// show a preview whenever one can be produced: SVG (rendered by the browser) or any format the
// image driver can actually rasterise here (images always, plus e.g. PDF with Imagick + Ghostscript)
if ($isSvg || MediaManager::canPreview($fileExt)) {
    if (!is_file(Path::media($fname))) {
        $sidebar = '<i class="rex-mime rex-mime-error"></i><span class="sr-only">' . $fname . '</span>';
    } else {
        if ($isSvg) {
            $rfwidth = min($fwidth, 200);
            $width = $rfwidth > 0 ? ' width="' . $rfwidth . '"' : '';
            $imgn = Url::media($fname) . '?buster=' . $gf->getDateTimeValue('updatedate');
            $imgMax = Url::media($fname);
        } else {
            $width = '';
            $imgn = MediaManager::getUrl('rex_media_medium', $encodedFname, $gf->getDateTimeValue('updatedate'));
            $imgMax = MediaManager::getUrl('rex_media_large', $encodedFname, $gf->getDateTimeValue('updatedate'));
        }

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

$openerLink = '';
if ('' !== $picker) {
    $openerLink = '<button ' . new HtmlAttributes([
        'type' => 'button',
        'class' => ['btn', 'btn-xs', 'btn-select', 'btn-highlight' => 'multiple' === $picker],
        'data-rex-pick' => $fname,
        'data-rex-pick-label' => $gf->getStringValue('title'),
    ])->toString() . '>' . I18n::msg('pool_file_get') . '</button>';

    $openerLink = ' | ' . $openerLink;
}

$sidebar = Extension::dispatch(new MediaDetailSidebar($media, $sidebar));

if ($TPERM) {
    $panel = '';

    $catsSel = new SelectInput(
        'rex_file_category',
        new MediaCategoryChoices(root: true)->choices(),
        (string) $rexFileCategory,
        'rex-mediapool-category',
        search: true,
    );

    $formElements = [];

    $e = [];
    $e['label'] = '<label for="rex-mediapool-title">' . I18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . escape($ftitle) . '" />';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label for="rex-mediapool-category">' . I18n::msg('pool_file_category') . '</label>';
    $e['field'] = $catsSel->render();
    $formElements[] = $e;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $meta ??= new MetaForm(new MetaContext(MetaEntity::Media, $media, mediaCategory: $rexFileCategory > 0 ? MediaCategory::get($rexFileCategory) : null));
    $panel .= $meta->render();
    $panel .= Extension::dispatch(new MediaFormEdit($media));

    $panel .= $addExtInfo;

    $formElements = [];

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_filename') . '</label>';
    $e['field'] = '<p class="form-control-static rex-word-break"><a href="' . Url::media($encodedFname) . '">' . escape($fname) . '</a> <span class="rex-filesize">' . $ffileSize . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_last_update') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . escape($gf->getStringValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . escape($gf->getStringValue('createuser')) . '</span></p>';
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
        <form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
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
        $catname = $Cat->name;
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
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('updatedate')) . ' <span class="rex-author">' . escape($gf->getStringValue('updateuser')) . '</span></p>';
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_created') . '</label>';
    $e['field'] = '<p class="form-control-static">' . Formatter::intlDateTime($gf->getDateTimeValue('createdate')) . ' <span class="rex-author">' . escape($gf->getStringValue('createuser')) . '</span></p>';
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
