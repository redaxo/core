<?php

/**
 * Funktionensammlung für den Medienpool.
 *
 * @package redaxo\mediapool
 */

/**
 * Erstellt einen Filename der eindeutig ist für den Medienpool.
 *
 * @param string $mediaName Dateiname
 * @param bool $doSubindexing
 *
 * @deprecated since 2.11, use `rex_mediapool::filename` instead
 */
function rex_mediapool_filename($mediaName, $doSubindexing = true): string
{
    return rex_mediapool::filename($mediaName, $doSubindexing);
}

/**
 * Holt ein upgeloadetes File und legt es in den Medienpool
 * Dabei wird kontrolliert ob das File schon vorhanden ist und es
 * wird eventuell angepasst, weiterhin werden die Fileinformationen übergeben.
 *
 * @param array $FILE
 * @param int $rexFileCategory
 * @param array $FILEINFOS
 * @param string $userlogin
 * @param bool $doSubindexing
 *
 * @return array
 * @deprecated since 2.11, use `rex_media_service::addMedia` instead
 * @phpstan-ignore function.unusedParameter
 */
function rex_mediapool_saveMedia($FILE, $rexFileCategory, $FILEINFOS, $userlogin = null, $doSubindexing = true)
{
    $data = $FILEINFOS;
    $data['category_id'] = (int) $rexFileCategory;

    if ($FILE) {
        $data['file'] = $FILE;

        if (!isset($data['file']['path']) && isset($FILE['name']) && !isset($FILE['tmp_name'])) {
            $data['file']['path'] = rex_path::media($FILE['name']);
        }
    }

    try {
        return rex_media_service::addMedia($data, $doSubindexing);
    } catch (rex_api_exception $e) {
        // BC
        // Missing Fields
        $RETURN = [];
        $RETURN['title'] = $FILEINFOS['title'];
        // $RETURN['type'] = $FILETYPE;
        $RETURN['msg'] = $e->getMessage();
        $RETURN['ok'] = 0;
        // $RETURN['filename'] = $NFILENAME;
        // $RETURN['old_filename'] = $FILENAME;
        $RETURN['category_id'] = $rexFileCategory;

        // if (isset($size) && $size) {
        // $RETURN['width'] = $size[0];
        // $RETURN['height'] = $size[1];
        // }

        return $RETURN;
    }
}

/**
 * Holt ein upgeloadetes File und legt es in den Medienpool
 * Dabei wird kontrolliert ob das File schon vorhanden ist und es
 * wird eventuell angepasst, weiterhin werden die Fileinformationen übergeben.
 *
 * @param array $FILE
 * @param array $FILEINFOS
 * @param string $userlogin
 *
 * @return array
 * @deprecated since 2.11, use `rex_media_service::updateMedia` instead
 * @phpstan-ignore function.unusedParameter
 */
function rex_mediapool_updateMedia($FILE, &$FILEINFOS, $userlogin = null)
{
    $data = [
        'category_id' => $FILEINFOS['rex_file_category'],
        'title' => $FILEINFOS['title'],
    ];

    if ($FILE && 'none' !== ($FILE['name'] ?? null)) {
        $data['file'] = $FILE;
    }

    try {
        return rex_media_service::updateMedia($FILEINFOS['filename'], $data);
    } catch (rex_api_exception $e) {
        return [
            'ok' => 0,
            'msg' => $e->getMessage(),
        ];
    }
}

/**
 * Synchronisiert die Datei $physical_filename des Mediafolders in den
 * Medienpool.
 *
 * @param string $physicalFilename
 * @param int $categoryId
 * @param string $title
 * @param int|null $filesize
 * @param string|null $filetype
 * @param string|null $userlogin
 *
 * @return array
 * @deprecated since 2.11, use `rex_media_service::addMedia` instead
 * @phpstan-ignore function.unusedParameter, function.unusedParameter, function.unusedParameter
 */
function rex_mediapool_syncFile($physicalFilename, $categoryId, $title, $filesize = null, $filetype = null, $userlogin = null)
{
    $data = [];
    $data['title'] = $title;
    $data['category_id'] = $categoryId;
    $data['file'] = [
        'name' => $physicalFilename,
        'path' => rex_path::media($physicalFilename),
    ];

    try {
        return rex_media_service::addMedia($data, false);
    } catch (rex_api_exception $e) {
        return [
            'ok' => 0,
            'msg' => $e->getMessage(),
        ];
    }
}

/**
 * @param string $filename
 *
 * @return array{ok: bool, msg: string}
 *
 * @deprecated since 2.11, use `rex_media_service::deleteMedia` instead
 */
function rex_mediapool_deleteMedia($filename)
{
    try {
        rex_media_service::deleteMedia($filename);
        return ['ok' => true, 'msg' => rex_i18n::msg('pool_file_deleted')];
    } catch (rex_api_exception $exception) {
        return ['ok' => false, 'msg' => '<strong>' . $exception->getMessage() . '</strong>'];
    }
}

/**
 * @param string $filename
 *
 * @return bool|string
 * @deprecated since 2.11, use `rex_mediapool::mediaIsInUse` instead
 */
function rex_mediapool_mediaIsInUse($filename)
{
    return rex_mediapool::mediaIsInUse($filename);
}

/**
 * Ausgabe des Medienpool Formulars.
 *
 * @return string
 */
function rex_mediapool_Mediaform($formTitle, $buttonTitle, $rexFileCategory, $fileChooser, $closeForm)
{
    global $ftitle, $warning, $info;

    $s = '';

    $catsSel = new rex_media_category_select();
    $catsSel->setStyle('class="form-control"');
    $catsSel->setSize(1);
    $catsSel->setName('rex_file_category');
    $catsSel->setId('rex-mediapool-category');
    $catsSel->setAttribute('class', 'selectpicker form-control');
    $catsSel->setAttribute('data-live-search', 'true');
    $catsSel->setAttribute('onchange', 'this.form.submit()');
    $catsSel->setSelected($rexFileCategory);

    if (rex::requireUser()->getComplexPerm('media')->hasAll()) {
        $catsSel->addOption(rex_i18n::msg('pool_kats_no'), '0');
    }

    if (isset($warning)) {
        if (is_array($warning)) {
            if (count($warning) > 0) {
                $s .= rex_view::error(implode('<br />', $warning));
            }
        } elseif ('' != $warning) {
            $s .= rex_view::error($warning);
        }
        $warning = '';
    }

    if (isset($info)) {
        if (is_array($info)) {
            if (count($info) > 0) {
                $s .= rex_view::success(implode('<br />', $info));
            }
        } elseif ('' != $info) {
            $s .= rex_view::success($info);
        }
        $info = '';
    }

    if (!isset($ftitle)) {
        $ftitle = '';
    }

    $argFields = '';
    foreach (rex_request('args', 'array') as $argName => $argValue) {
        $argFields .= '<input type="hidden" name="args[' . rex_escape($argName) . ']" value="' . rex_escape($argValue) . '" />' . "\n";
    }

    $openerInputField = rex_request('opener_input_field', 'string');
    if ('' != $openerInputField) {
        $argFields .= '<input type="hidden" name="opener_input_field" value="' . rex_escape($openerInputField) . '" />' . "\n";
    }

    $addSubmit = '';
    if ($closeForm && '' != $openerInputField) {
        $addSubmit = '<button class="btn btn-save" type="submit" name="saveandexit" value="' . rex_i18n::msg('pool_file_upload_get') . '"' . rex::getAccesskey(rex_i18n::msg('save_and_close_tooltip'), 'save') . '>' . rex_i18n::msg('pool_file_upload_get') . '</button>';
    }

    $panel = '';
    $panel .= '<fieldset>';
    $formElements = [];

    $e = [];
    $e['label'] = '<label for="rex-mediapool-category">' . rex_i18n::msg('pool_file_category') . '</label>';
    $e['field'] = $catsSel->get();
    $formElements[] = $e;

    $e = [];
    $e['label'] = '<label for="rex-mediapool-title">' . rex_i18n::msg('pool_file_title') . '</label>';
    $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . rex_escape($ftitle) . '" maxlength="255" />';
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= rex_extension::registerPoint(new rex_extension_point('MEDIA_FORM_ADD', ''));

    if ($fileChooser) {
        $e = [];
        $e['label'] = '<label for="rex-mediapool-choose-file">' . rex_i18n::msg('pool_file_file') . '</label>';
        $e['field'] = '<input id="rex-mediapool-choose-file" type="file" name="file_new" />';
        $e['after'] = '<h3>' . rex_i18n::msg('phpini_settings') . '</h3>
                        <dl class="dl-horizontal text-left">
                        ' . ((0 == rex_ini_get('file_uploads')) ? '<dt><span class="text-warning">' . rex_i18n::msg('pool_upload') . '</span></dt><dd><span class="text-warning">' . rex_i18n::msg('pool_upload_disabled') . '</span></dd>' : '') . '
                            <dt>' . rex_i18n::msg('pool_max_uploadsize') . ':</dt><dd>' . rex_formatter::bytes(rex_request::getMaxUploadSize()) . '</dd>
                            <dt>' . rex_i18n::msg('pool_max_uploadtime') . ':</dt><dd>' . rex_ini_get('max_input_time') . 's</dd>
                        </dl>';

        $fragment = new rex_fragment();
        $fragment->setVar('elements', [$e], false);
        $panel .= $fragment->parse('core/form/form.php');
    }
    $panel .= '</fieldset>';

    $formElements = [];

    $e = [];
    $e['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $buttonTitle . '"' . rex::getAccesskey($buttonTitle, 'save') . '>' . $buttonTitle . '</button>';
    $formElements[] = $e;

    $e = [];
    $e['field'] = $addSubmit;
    $formElements[] = $e;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formTitle, false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content = $fragment->parse('core/page/section.php');

    $s .= ' <form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data" data-pjax="false">
                ' . rex_csrf_token::factory('mediapool')->getHiddenField() . '
                <fieldset>
                    <input type="hidden" name="media_method" value="add_file" />
                    ' . $argFields . '
                    ' . $content . '
                </fieldset>
            ';

    if ($closeForm) {
        $s .= '</form>' . "\n";
    }

    return $s;
}

/**
 * Ausgabe des Medienpool Upload-Formulars.
 * @return string
 */
function rex_mediapool_Uploadform($rexFileCategory)
{
    return rex_mediapool_Mediaform(rex_i18n::msg('pool_file_insert'), rex_i18n::msg('pool_file_upload'), $rexFileCategory, true, true);
}

/**
 * Ausgabe des Medienpool Sync-Formulars.
 * @return string
 */
function rex_mediapool_Syncform($rexFileCategory)
{
    return rex_mediapool_Mediaform(rex_i18n::msg('pool_sync_title'), rex_i18n::msg('pool_sync_button'), $rexFileCategory, false, false);
}

/**
 * check if mediatpye(extension) is allowed for upload.
 *
 * @param string $filename
 *
 * @return bool
 * @deprecated since 2.11, use `rex_mediapool::isAllowedExtension` instead
 */
function rex_mediapool_isAllowedMediaType($filename, array $args = [])
{
    return rex_mediapool::isAllowedExtension($filename, $args);
}

/**
 * Checks file against optional property `allowed_mime_types`.
 *
 * @param string $path Path to the physical file
 * @param string|null $filename Optional filename, will be used for extracting the file extension.
 *                              If not given, the extension is extracted from `$path`.
 *
 * @return bool
 * @deprecated since 2.11, use `rex_mediapool::isAllowedMimeType` instead
 */
function rex_mediapool_isAllowedMimeType($path, $filename = null)
{
    return rex_mediapool::isAllowedMimeType($path, $filename);
}

/**
 * Get allowed mediatype extensions given via media widget "types" param.
 *
 * @param array $args widget params
 *
 * @return array allowed extensions
 * @deprecated since 2.11, use `rex_mediapool::getAllowedExtensions` instead
 */
function rex_mediapool_getMediaTypeWhitelist($args = [])
{
    return rex_mediapool::getAllowedExtensions($args);
}

/**
 * Get global blocked mediatype extensions.
 *
 * @return array blocked mediatype extensions
 * @deprecated since 2.11, use `rex_mediapool::getBlockedExtensions` instead
 */
function rex_mediapool_getMediaTypeBlacklist()
{
    return rex_mediapool::getBlockedExtensions();
}
