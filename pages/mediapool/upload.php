<?php

use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

assert(isset($PERMALL) && is_bool($PERMALL));
assert(isset($picker) && is_string($picker));
assert(isset($rexFileCategory) && is_int($rexFileCategory));

/** @var array{types?: string, picker?: string, language?: int} $argUrl */
$argUrl ??= [];

if (!$PERMALL && !Core::requireUser()->getComplexPerm('media')->hasCategoryPerm($rexFileCategory)) {
    $rexFileCategory = 0;
}

$mediaMethod = Request::request('media_method', 'string');
$meta = new MetaForm(new MetaContext(MetaEntity::Media, mediaCategory: $rexFileCategory > 0 ? MediaCategory::get($rexFileCategory) : null));
$csrf = CsrfToken::factory('mediapool');

$info = '';
$warning = '';
if ('add_file' == $mediaMethod) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        if (Request::post('save', 'boolean') || Request::post('saveandexit', 'boolean')) {
            $types = Request::post('types', 'string');

            try {
                /** @var mixed $file */
                $file = Core::getRequest()->files->get('file_new');
                if (!$file instanceof UploadedFile) {
                    throw new UserMessageException(I18n::msg('pool_file_not_found'));
                }

                if (!$meta->submit()) {
                    throw new UserMessageException(I18n::msg('minfo_metadata_invalid'));
                }

                $data = [
                    'file' => $file,
                    'category_id' => $rexFileCategory ?: null,
                    'title' => Request::request('ftitle', 'string'),
                ];

                $media = MediaHandler::add($data, $meta->values(), types: '' === $types ? [] : explode(',', $types));
                $info = I18n::msg('pool_file_added');
                // in picker mode "save and pick" hands the new medium to the overlay, see pages/mediapool/index.php
                $picked = '' !== $picker && Request::post('saveandexit', 'boolean') ? ['picked' => $media->fileName] : [];

                Response::sendRedirect(Url::backendPage('mediapool/media', ['info' => $info, ...$argUrl, ...$picked]));
            } catch (UserMessageException $e) {
                $warning = $e->getMessage();
            }
        }
    }
}

if ('' !== $warning) {
    echo Message::error($warning);
}

if ('' !== $info) {
    echo Message::success($info);
}

echo View::mediaPoolMediaForm(I18n::msg('pool_file_insert'), I18n::msg('pool_file_upload'), $rexFileCategory, true, true, $meta);
