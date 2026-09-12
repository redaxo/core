<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormAdd;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormButtons;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormEdit;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

/** Verwaltung der Content Sprachen. */

$content = '';
$message = '';

// -------------- Defaults
$languageId = Request::request('language_id', 'int');
$languageCode = Request::request('language_code', 'string');
$languageName = Request::request('language_name', 'string');
$languagePrio = Request::request('language_prio', 'int');
$languageStatus = Request::request('language_status', 'bool');
$func = Request::request('func', 'string');

// -------------- Form Submits
$addClangSave = Request::post('add_language_save', 'boolean');
$editClangSave = Request::post('edit_language_save', 'boolean');

$error = '';
$success = '';

$csrfToken = CsrfToken::factory('language');

// ----- delete language
if ('delete' == $func && '' != $languageId && Language::exists($languageId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new UserMessageException(I18n::msg('csrf_token_invalid'));
        }
        LanguageHandler::delete($languageId);
        $success = I18n::msg('language_deleted');
        $func = '';
        $languageId = 0;
    } catch (UserMessageException $e) {
        echo Message::error($e->getMessage());
    }
}

if ('editstatus' === $func && Language::exists($languageId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new UserMessageException(I18n::msg('csrf_token_invalid'));
        }
        $language = Language::require($languageId);
        LanguageHandler::edit($languageId, $language->code, $language->name, $language->priority, $languageStatus);
        $success = I18n::msg('language_edited');
        $func = '';
        $languageId = 0;
    } catch (UserMessageException $e) {
        echo Message::error($e->getMessage());
    }
}

// ----- add language
if ($addClangSave || $editClangSave) {
    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ('' == $languageCode) {
        $error = I18n::msg('enter_code');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ('' == $languageName) {
        $error = I18n::msg('enter_name');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ($addClangSave) {
        $success = I18n::msg('language_created');
        LanguageHandler::add($languageCode, $languageName, $languagePrio);
        $languageId = 0;
        $func = '';
    } else {
        if (Language::exists($languageId)) {
            LanguageHandler::edit($languageId, $languageCode, $languageName, $languagePrio);
            $success = I18n::msg('language_edited');
            $func = '';
            $languageId = 0;
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
                    <th class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['func' => 'add']) . '#language"' . Accesskey::attributes(I18n::msg('language_add'), 'add') . '><i class="rex-icon rex-icon-add-language"></i></a></th>
                    <th class="rex-table-id">' . I18n::msg('id') . '</th>
                    <th>' . I18n::msg('language_code') . '</th>
                    <th>' . I18n::msg('language_name') . '</th>
                    <th class="rex-table-priority">' . I18n::msg('language_priority') . '</th>
                    <th class="rex-table-action" colspan="3">' . I18n::msg('language_function') . '</th>
                </tr>
            </thead>
            <tbody>
    ';

// Add form
if ('add' == $func) {
    // ----- EXTENSION POINT
    $metaButtons = Extension::dispatch(new LanguageFormButtons());

    // ggf wiederanzeige des add forms, falls ungueltige id uebermittelt
    $content .= '
                <tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('id') . '">–</td>
                    <td data-title="' . I18n::msg('language_code') . '"><input class="form-control" type="text" id="rex-form-language-code" name="language_code" value="' . escape($languageCode) . '" required maxlength="35" autocapitalize="off" autocorrect="off" autofocus /></td>
                    <td data-title="' . I18n::msg('language_name') . '"><input class="form-control" type="text" id="rex-form-language-name" name="language_name" value="' . escape($languageName) . '" required maxlength="255" /></td>
                    <td class="rex-table-priority" data-title="' . I18n::msg('language_priority') . '"><input class="form-control" type="number" id="rex-form-language-prio" name="language_prio" value="' . ($languagePrio ?: Language::count() + 1) . '" required min="1" inputmode="numeric" /></td>
                    <td class="rex-table-action">' . $metaButtons . '</td>
                    <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="add_language_save"' . Accesskey::attributes(I18n::msg('language_add'), 'save') . ' value="1">' . I18n::msg('language_add') . '</button></td>
                </tr>
            ';

    // ----- EXTENSION POINT
    $content .= Extension::dispatch(new LanguageFormAdd());
}

$sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('language') . ' ORDER BY priority');
foreach ($sql as $row) {
    $langId = (int) $sql->getValue('id');
    $addTd = '<td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . $langId . '</td>';

    $delLink = I18n::msg('delete');
    if ($langId == Language::getStartId()) {
        $delLink = '<span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</span>';
    } else {
        $delLink = '<a class="rex-link-expanded" href="' . Url::currentBackendPage(['func' => 'delete', 'language_id' => $langId] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</a>';
    }

    // Edit form
    if ('edit' == $func && $languageId == $langId) {
        // ----- EXTENSION POINT
        $metaButtons = Extension::dispatch(new LanguageFormButtons(Language::require($langId)));

        $content .= '
                    <tr class="mark">
                        <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                        ' . $addTd . '
                        <td data-title="' . I18n::msg('language_code') . '"><input class="form-control" type="text" id="rex-form-language-code" name="language_code" value="' . escape($sql->getValue('code')) . '" required maxlength="35" autocapitalize="off" autocorrect="off" autofocus /></td>
                        <td data-title="' . I18n::msg('language_name') . '"><input class="form-control" type="text" id="rex-form-language-name" name="language_name" value="' . escape($sql->getValue('name')) . '" required maxlength="255" /></td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('language_priority') . '"><input class="form-control" type="number" id="rex-form-language-prio" name="language_prio" value="' . escape($sql->getValue('priority')) . '" required min="1" inputmode="numeric" /></td>
                        <td class="rex-table-action">' . $metaButtons . '</td>
                        <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="edit_language_save"' . Accesskey::attributes(I18n::msg('language_update'), 'save') . ' value="1">' . I18n::msg('language_update') . '</button></td>
                    </tr>';

        // ----- EXTENSION POINT
        $content .= Extension::dispatch(new LanguageFormEdit(Language::require($langId)));
    } else {
        $editLink = Url::currentBackendPage(['func' => 'edit', 'language_id' => $langId]) . '#rex-form-system-language';

        $status = $sql->getValue('status') ? 'online' : 'offline';

        $content .= '
                    <tr>
                        <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $editLink . '" title="' . escape($languageName) . '"><i class="rex-icon rex-icon-language"></i></a></td>
                        ' . $addTd . '
                        <td data-title="' . I18n::msg('language_code') . '">' . escape($sql->getValue('code')) . '</td>
                        <td data-title="' . I18n::msg('language_name') . '">' . escape($sql->getValue('name')) . '</td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('language_priority') . '">' . escape($sql->getValue('priority')) . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $editLink . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit') . '</a></td>
                        <td class="rex-table-action">' . $delLink . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded rex-' . $status . '" href="' . Url::currentBackendPage(['language_id' => $langId, 'func' => 'editstatus', 'language_status' => $sql->getValue('status') ? 0 : 1] + $csrfToken->getUrlParams()) . '"><i class="rex-icon rex-icon-' . $status . '"></i> ' . I18n::msg('language_' . $status) . '</a></td>
                    </tr>';
    }
}

$content .= '
        </tbody>
    </table>';

echo $message;

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('language_caption'), false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

if ('add' == $func || 'edit' == $func) {
    $content = '
        <form id="rex-form-system-language" action="' . Url::currentBackendPage() . '" method="post">
            <fieldset>
                <input type="hidden" name="language_id" value="' . $languageId . '" />
                ' . $csrfToken->getHiddenField() . '
                ' . $content . '
            </fieldset>
        </form>
        ';
}

echo $content;
