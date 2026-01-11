<?php

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

assert(isset($PERMALL) && is_bool($PERMALL));
assert(isset($openerInputField) && is_string($openerInputField));

if (!isset($rexFileCategory)) {
    $rexFileCategory = 0;
}

if (!$PERMALL && !Core::requireUser()->getComplexPerm('media')->hasCategoryPerm($rexFileCategory)) {
    $rexFileCategory = 0;
}

$mediaMethod = Request::request('media_method', 'string');
$csrf = CsrfToken::factory('mediapool');

$info = '';
$warning = '';
if ('add_file' == $mediaMethod) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        if (Request::post('save', 'boolean') || Request::post('saveandexit', 'boolean')) {
            $data = [];
            $data['title'] = Request::request('ftitle', 'string');
            $data['category_id'] = (int) $rexFileCategory;
            $data['file'] = Request::files('file_new', [
                ['name', 'string'],
                ['tmp_name', 'string'],
                ['error', 'int'],
            ]);

            try {
                $data = MediaHandler::addMedia($data, true, Request::post('args', 'array'));
                $info = I18n::msg('pool_file_added');
                if (Request::post('saveandexit', 'boolean')) {
                    if ('' != $openerInputField) {
                        if (str_starts_with($openerInputField, 'REX_MEDIALIST_')) {
                            $js = "selectMedialist('" . $data['filename'] . "');";
                            $js .= 'location.href = "' . Url::backendPage('mediapool', ['info' => $info, 'opener_input_field' => $openerInputField]) . '";';
                        } else {
                            $js = "selectMedia('" . $data['filename'] . "');";
                        }
                    }

                    echo '<script type="text/javascript" nonce="' . Response::getNonce() . '">';
                    if (isset($js)) {
                        echo $js;
                    }
                    echo '</script>';
                    exit;
                }

                Response::sendRedirect(Url::backendPage('mediapool/media', ['info' => $info, 'opener_input_field' => $openerInputField]));
            } catch (ApiFunctionException $e) {
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

echo View::mediaPoolMediaForm(I18n::msg('pool_file_insert'), I18n::msg('pool_file_upload'), $rexFileCategory, true, true);
