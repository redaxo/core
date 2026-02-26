<?php

/** Verwaltung der Content Sprachen. */

$content = '';
$message = '';

// -------------- Defaults
$clangId = rex_request('clang_id', 'int');
$clangCode = rex_request('clang_code', 'string');
$clangName = rex_request('clang_name', 'string');
$clangPrio = rex_request('clang_prio', 'int');
$clangStatus = rex_request('clang_status', 'bool');
$func = rex_request('func', 'string');

// -------------- Form Submits
$addClangSave = rex_post('add_clang_save', 'boolean');
$editClangSave = rex_post('edit_clang_save', 'boolean');

$error = '';
$success = '';

$csrfToken = rex_csrf_token::factory('clang');

// ----- delete clang
if ('deleteclang' == $func && '' != $clangId && rex_clang::exists($clangId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new rex_functional_exception(rex_i18n::msg('csrf_token_invalid'));
        }
        rex_clang_service::deleteCLang($clangId);
        $success = rex_i18n::msg('clang_deleted');
        $func = '';
        $clangId = 0;
    } catch (rex_functional_exception $e) {
        echo rex_view::error($e->getMessage());
    }
}

if ('editstatus' === $func && rex_clang::exists($clangId)) {
    try {
        if (!$csrfToken->isValid()) {
            throw new rex_functional_exception(rex_i18n::msg('csrf_token_invalid'));
        }
        $clang = rex_clang::get($clangId);
        rex_clang_service::editCLang($clangId, $clang->getCode(), $clang->getName(), $clang->getPriority(), $clangStatus);
        $success = rex_i18n::msg('clang_edited');
        $func = '';
        $clangId = 0;
    } catch (rex_functional_exception $e) {
        echo rex_view::error($e->getMessage());
    }
}

// ----- add clang
if ($addClangSave || $editClangSave) {
    if (!$csrfToken->isValid()) {
        $error = rex_i18n::msg('csrf_token_invalid');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ('' == $clangCode) {
        $error = rex_i18n::msg('enter_code');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ('' == $clangName) {
        $error = rex_i18n::msg('enter_name');
        $func = $addClangSave ? 'addclang' : 'editclang';
    } elseif ($addClangSave) {
        $success = rex_i18n::msg('clang_created');
        rex_clang_service::addCLang($clangCode, $clangName, $clangPrio);
        $clangId = 0;
        $func = '';
    } else {
        if (rex_clang::exists($clangId)) {
            rex_clang_service::editCLang($clangId, $clangCode, $clangName, $clangPrio);
            $success = rex_i18n::msg('clang_edited');
            $func = '';
            $clangId = 0;
        }
    }
}

if ('' != $success) {
    $message .= rex_view::success($success);
}

if ('' != $error) {
    $message .= rex_view::error($error);
}

$content .= '
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th class="rex-table-icon"><a class="rex-link-expanded" href="' . rex_url::currentBackendPage(['func' => 'addclang']) . '#clang"' . rex::getAccesskey(rex_i18n::msg('clang_add'), 'add') . '><i class="rex-icon rex-icon-add-language"></i></a></th>
                    <th class="rex-table-id">' . rex_i18n::msg('id') . '</th>
                    <th>' . rex_i18n::msg('clang_code') . '</th>
                    <th>' . rex_i18n::msg('clang_name') . '</th>
                    <th class="rex-table-priority">' . rex_i18n::msg('clang_priority') . '</th>
                    <th class="rex-table-action" colspan="3">' . rex_i18n::msg('clang_function') . '</th>
                </tr>
            </thead>
            <tbody>
    ';

// Add form
if ('addclang' == $func) {
    // ----- EXTENSION POINT
    $metaButtons = rex_extension::registerPoint(new rex_extension_point('CLANG_FORM_BUTTONS', ''));

    // ggf wiederanzeige des add forms, falls ungueltige id uebermittelt
    $content .= '
                <tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                    <td class="rex-table-id" data-title="' . rex_i18n::msg('id') . '">–</td>
                    <td data-title="' . rex_i18n::msg('clang_code') . '"><input class="form-control" type="text" id="rex-form-clang-code" name="clang_code" value="' . rex_escape($clangCode) . '" required maxlength="255" autocapitalize="off" autocorrect="off" autofocus /></td>
                    <td data-title="' . rex_i18n::msg('clang_name') . '"><input class="form-control" type="text" id="rex-form-clang-name" name="clang_name" value="' . rex_escape($clangName) . '" required maxlength="255" /></td>
                    <td class="rex-table-priority" data-title="' . rex_i18n::msg('clang_priority') . '"><input class="form-control" type="number" id="rex-form-clang-prio" name="clang_prio" value="' . ($clangPrio ?: rex_clang::count() + 1) . '" required min="1" inputmode="numeric" /></td>
                    <td class="rex-table-action">' . $metaButtons . '</td>
                    <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="add_clang_save"' . rex::getAccesskey(rex_i18n::msg('clang_add'), 'save') . ' value="1">' . rex_i18n::msg('clang_add') . '</button></td>
                </tr>
            ';

    // ----- EXTENSION POINT
    $content .= rex_extension::registerPoint(new rex_extension_point('CLANG_FORM_ADD', ''));
}

$sql = rex_sql::factory()->setQuery('SELECT * FROM ' . rex::getTable('clang') . ' ORDER BY priority');
foreach ($sql as $row) {
    $langId = (int) $sql->getValue('id');
    $addTd = '<td class="rex-table-id" data-title="' . rex_i18n::msg('id') . '">' . $langId . '</td>';

    $delLink = rex_i18n::msg('delete');
    if ($langId == rex_clang::getStartId()) {
        $delLink = '<span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</span>';
    } else {
        $delLink = '<a class="rex-link-expanded" href="' . rex_url::currentBackendPage(['func' => 'deleteclang', 'clang_id' => $langId] + $csrfToken->getUrlParams()) . '" data-confirm="' . rex_i18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . $delLink . '</a>';
    }

    // Edit form
    if ('editclang' == $func && $clangId == $langId) {
        // ----- EXTENSION POINT
        $metaButtons = rex_extension::registerPoint(new rex_extension_point('CLANG_FORM_BUTTONS', '', ['id' => $clangId, 'sql' => $sql]));

        $content .= '
                    <tr class="mark">
                        <td class="rex-table-icon"><i class="rex-icon rex-icon-language"></i></td>
                        ' . $addTd . '
                        <td data-title="' . rex_i18n::msg('clang_code') . '"><input class="form-control" type="text" id="rex-form-clang-code" name="clang_code" value="' . rex_escape($sql->getValue('code')) . '" required maxlength="255" autocapitalize="off" autocorrect="off" autofocus /></td>
                        <td data-title="' . rex_i18n::msg('clang_name') . '"><input class="form-control" type="text" id="rex-form-clang-name" name="clang_name" value="' . rex_escape($sql->getValue('name')) . '" required maxlength="255" /></td>
                        <td class="rex-table-priority" data-title="' . rex_i18n::msg('clang_priority') . '"><input class="form-control" type="number" id="rex-form-clang-prio" name="clang_prio" value="' . rex_escape($sql->getValue('priority')) . '" required min="1" inputmode="numeric" /></td>
                        <td class="rex-table-action">' . $metaButtons . '</td>
                        <td class="rex-table-action" colspan="2"><button class="btn btn-save" type="submit" name="edit_clang_save"' . rex::getAccesskey(rex_i18n::msg('clang_update'), 'save') . ' value="1">' . rex_i18n::msg('clang_update') . '</button></td>
                    </tr>';

        // ----- EXTENSION POINT
        $content .= rex_extension::registerPoint(new rex_extension_point('CLANG_FORM_EDIT', '', ['id' => $clangId, 'sql' => $sql]));
    } else {
        $editLink = rex_url::currentBackendPage(['func' => 'editclang', 'clang_id' => $langId]) . '#clang';

        $status = $sql->getValue('status') ? 'online' : 'offline';

        $content .= '
                    <tr>
                        <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $editLink . '" title="' . rex_escape($clangName) . '"><i class="rex-icon rex-icon-language"></i></a></td>
                        ' . $addTd . '
                        <td data-title="' . rex_i18n::msg('clang_code') . '">' . rex_escape($sql->getValue('code')) . '</td>
                        <td data-title="' . rex_i18n::msg('clang_name') . '">' . rex_escape($sql->getValue('name')) . '</td>
                        <td class="rex-table-priority" data-title="' . rex_i18n::msg('clang_priority') . '">' . rex_escape($sql->getValue('priority')) . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $editLink . '"><i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit') . '</a></td>
                        <td class="rex-table-action">' . $delLink . '</td>
                        <td class="rex-table-action"><a class="rex-link-expanded rex-' . $status . '" href="' . rex_url::currentBackendPage(['clang_id' => $langId, 'func' => 'editstatus', 'clang_status' => $sql->getValue('status') ? 0 : 1] + $csrfToken->getUrlParams()) . '"><i class="rex-icon rex-icon-' . $status . '"></i> ' . rex_i18n::msg('clang_' . $status) . '</a></td>
                    </tr>';
    }
}

$content .= '
        </tbody>
    </table>';

echo $message;

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('clang_caption'), false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

if ('addclang' == $func || 'editclang' == $func) {
    $content = '
        <form id="rex-form-system-language" action="' . rex_url::currentBackendPage() . '" method="post">
            <fieldset>
                <input type="hidden" name="clang_id" value="' . $clangId . '" />
                ' . $csrfToken->getHiddenField() . '
                ' . $content . '
            </fieldset>
        </form>
        ';
}

echo $content;
