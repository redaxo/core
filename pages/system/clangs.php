<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

/**
 * Verwaltung der Content Sprachen.
 */

$content = '';
$message = '';

// -------------- Defaults
$clangId = Request::request('clang_id', 'int');
$clangCode = Request::request('clang_code', 'string');
$clangName = Request::request('clang_name', 'string');
$clangPrio = Request::request('clang_prio', 'int');
$clangStatus = Request::request('clang_status', 'bool');
$func = Request::request('func', 'string');

// -------------- Form Submits
$addClangSave = Request::post('add_clang_save', 'boolean');
$editClangSave = Request::post('edit_clang_save', 'boolean');

$error = '';
$success = '';

$csrfToken = CsrfToken::factory('clang');

// ----- delete clang
if ('deleteclang' == $func && '' != $clangId && Language::exists($clangId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new UserMessageException(I18n::msg('csrf_token_invalid'));
        }
        LanguageHandler::deleteCLang($clangId);
        $success = I18n::msg('clang_deleted');
        $func = '';
        $clangId = 0;
    } catch (UserMessageException $e) {
        echo Message::error($e->getMessage());
    }
}

if ('editstatus' === $func && Language::exists($clangId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new UserMessageException(I18n::msg('csrf_token_invalid'));
        }
        $clang = Language::get($clangId);
        LanguageHandler::editCLang($clangId, $clang->getCode(), $clang->getName(), $clang->getPriority(), $clangStatus);
        $success = I18n::msg('clang_edited');
        $func = '';
        $clangId = 0;
    } catch (UserMessageException $e) {
        echo Message::error($e->getMessage());
    }
}

// ----- add clang
if ($addClangSave || $editClangSave) {
    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ('' == $clangCode) {
        $error = I18n::msg('enter_code');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ('' == $clangName) {
        $error = I18n::msg('enter_name');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ($addClangSave) {
        $success = I18n::msg('clang_created');
        LanguageHandler::addCLang($clangCode, $clangName, $clangPrio);
        $clangId = 0;
        $func = '';
    } else {
        if (Language::exists($clangId)) {
            LanguageHandler::editCLang($clangId, $clangCode, $clangName, $clangPrio);
            $success = I18n::msg('clang_edited');
            $func = '';
            $clangId = 0;
        }
    }
}

if ('' != $success) {
    $message .= Message::success($success);
}

if ('' != $error) {
    $message .= Message::error($error);
}

$content .= '
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['func' => 'addclang']) . '#clang"' . Core::getAccesskey(I18n::msg('clang_add'), 'add') . '><i class="rex-icon rex-icon-add-language"></i></a></th>
                    <th class="rex-table-id">' . I18n::msg('id') . '</th>
                    <th>' . I18n::msg('clang_code') . '</th>
                    <th>' . I18n::msg('clang_name') . '</th>
                    <th class="rex-table-priority">' . I18n::msg('clang_priority') . '</th>
                    <th class="rex-table-action" colspan="3">' . I18n::msg('clang_function') . '</th>
                </tr>
            </thead>
            <tbody>
    ';

// Add form
if ('addclang' == $func) {
    // ----- EXTENSION POINT
    $metaButtons = Extension::registerPoint(new ExtensionPoint('CLANG_FORM_BUTTONS', ''));

    // ggf wiederanzeige des add forms, falls ungueltige id uebermittelt
    $content .= '
                <tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('id') . '">–</td>
                    <td data-title="' . I18n::msg('clang_code') . '"><input class="form-control" type="text" id="rex-form-clang-code" name="clang_code" value="' . escape($clangCode) . '" required maxlength="255" autocapitalize="off" autocorrect="off" autofocus /></td>
                    <td data-title="' . I18n::msg('clang_name') . '"><input class="form-control" type="text" id="rex-form-clang-name" name="clang_name" value="' . escape($clangName) . '" required maxlength="255" /></td>
                    <td class="rex-table-priority" data-title="' . I18n::msg('clang_priority') . '"><input class="form-control" type="number" id="rex-form-clang-prio" name="clang_prio" value="' . ($clangPrio ?: Language::count() + 1) . '" required min="1" inputmode="numeric" /></td>
                    <td class="rex-table-action">' . $metaButtons . '</td>
                    <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="add_clang_save"' . Core::getAccesskey(I18n::msg('clang_add'), 'save') . ' value="1">' . I18n::msg('clang_add') . '</button></td>
                </tr>
            ';

    // ----- EXTENSION POINT
    $content .= Extension::registerPoint(new ExtensionPoint('CLANG_FORM_ADD', ''));
}

$sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('clang') . ' ORDER BY priority');
foreach ($sql as $row) {
    $langId = (int) $sql->getValue('id');
    $addTd = '<td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . $langId . '</td>';

    $delLink = I18n::msg('delete');
    if ($langId == Language::getStartId()) {
        $delLink = '<span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</span>';
    } else {
        $delLink = '<a class="rex-link-expanded" href="' . Url::currentBackendPage(['func' => 'deleteclang', 'clang_id' => $langId] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</a>';
    }

    // Edit form
    if ('editclang' == $func && $clangId == $langId) {
        // ----- EXTENSION POINT
        $metaButtons = Extension::registerPoint(new ExtensionPoint('CLANG_FORM_BUTTONS', '', ['id' => $clangId, 'sql' => $sql]));

        $content .= '
                    <tr class="mark">
                        <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                        ' . $addTd . '
                        <td data-title="' . I18n::msg('clang_code') . '"><input class="form-control" type="text" id="rex-form-clang-code" name="clang_code" value="' . escape($sql->getValue('code')) . '" required maxlength="255" autocapitalize="off" autocorrect="off" autofocus /></td>
                        <td data-title="' . I18n::msg('clang_name') . '"><input class="form-control" type="text" id="rex-form-clang-name" name="clang_name" value="' . escape($sql->getValue('name')) . '" required maxlength="255" /></td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('clang_priority') . '"><input class="form-control" type="number" id="rex-form-clang-prio" name="clang_prio" value="' . escape($sql->getValue('priority')) . '" required min="1" inputmode="numeric" /></td>
                        <td class="rex-table-action">' . $metaButtons . '</td>
                        <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="edit_clang_save"' . Core::getAccesskey(I18n::msg('clang_update'), 'save') . ' value="1">' . I18n::msg('clang_update') . '</button></td>
                    </tr>';

        // ----- EXTENSION POINT
        $content .= Extension::registerPoint(new ExtensionPoint('CLANG_FORM_EDIT', '', ['id' => $clangId, 'sql' => $sql]));
    } else {
        $editLink = Url::currentBackendPage(['func' => 'editclang', 'clang_id' => $langId]) . '#clang';

        $status = $sql->getValue('status') ? 'online' : 'offline';

        $content .= '
                    <tr>
                        <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $editLink . '" title="' . escape($clangName) . '"><i class="rex-icon rex-icon-language"></i></a></td>
                        ' . $addTd . '
                        <td data-title="' . I18n::msg('clang_code') . '">' . escape($sql->getValue('code')) . '</td>
                        <td data-title="' . I18n::msg('clang_name') . '">' . escape($sql->getValue('name')) . '</td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('clang_priority') . '">' . escape($sql->getValue('priority')) . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $editLink . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit') . '</a></td>
                        <td class="rex-table-action">' . $delLink . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded rex-' . $status . '" href="' . Url::currentBackendPage(['clang_id' => $langId, 'func' => 'editstatus', 'clang_status' => $sql->getValue('status') ? 0 : 1] + $csrfToken->getUrlParams()) . '"><i class="rex-icon rex-icon-' . $status . '"></i> ' . I18n::msg('clang_' . $status) . '</a></td>
                    </tr>';
    }
}

$content .= '
        </tbody>
    </table>';

echo $message;

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('clang_caption'), false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

if ('addclang' == $func || 'editclang' == $func) {
    $content = '
        <form id="rex-form-system-language" action="' . Url::currentBackendPage() . '" method="post">
            <fieldset>
                <input type="hidden" name="clang_id" value="' . $clangId . '" />
                ' . $csrfToken->getHiddenField() . '
                ' . $content . '
            </fieldset>
        </form>
        ';
}

echo $content;
