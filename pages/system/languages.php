<?php

use Redaxo\Core\Backend\Accesskey;
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
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Component\TableColumnType;
use Redaxo\Core\View\Component\TableRow;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
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
        LanguageHandler::edit($languageId, ['status' => $languageStatus]);
        $success = I18n::msg('language_edited');
        $func = '';
        $languageId = 0;
    } catch (UserMessageException $e) {
        echo Message::error($e->getMessage());
    }
}

$meta = null;

// ----- add language
if ($addClangSave || $editClangSave) {
    $meta = new MetaForm(new MetaContext(MetaEntity::Language, $editClangSave ? Language::get($languageId) : null));
    $metaValid = $meta->submit();

    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ('' == $languageCode) {
        $error = I18n::msg('enter_code');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ('' == $languageName) {
        $error = I18n::msg('enter_name');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif (array_any(Language::getAll(), static fn (Language $language): bool => $language->id !== $languageId && $language->code === $languageCode)) {
        $error = I18n::msg('code_exists');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif (!$metaValid) {
        $error = I18n::msg('minfo_metadata_invalid');
        $func = $addClangSave ? 'add' : 'edit';
    } elseif ($addClangSave) {
        $success = I18n::msg('language_created');
        LanguageHandler::add(['code' => $languageCode, 'name' => $languageName, 'priority' => $languagePrio], $meta->values());
        $languageId = 0;
        $func = '';
    } else {
        if (Language::exists($languageId)) {
            LanguageHandler::edit($languageId, ['code' => $languageCode, 'name' => $languageName, 'priority' => $languagePrio], $meta->values());
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

$metaToggle = static fn (MetaForm $meta): string => $meta->hasFields()
    ? '<button type="button" class="rex-button' . ($meta->hasErrors() ? '' : ' collapsed') . '" data-toggle="collapse" data-target="#rex-language-metainfo" aria-controls="rex-language-metainfo" aria-expanded="' . ($meta->hasErrors() ? 'true' : 'false') . '"><i class="rex-icon rex-icon-structure-category-metainfo" aria-hidden="true"></i><span class="rex-u-visually-hidden">' . I18n::msg('metadata') . '</span></button>'
    : '';
$metaRow = static fn (MetaForm $meta): ?TableRow => $meta->hasFields()
    ? new TableRow([
        new TableCell(null, 2),
        new TableCell(Html::raw('<div class="rex-collapse-content">' . $meta->render() . '</div>'), 6),
    ], new HtmlAttributes(['id' => 'rex-language-metainfo', 'class' => ['collapse', 'in' => $meta->hasErrors(), 'rex-u-mark']]))
    : null;

$icon = Html::raw('<i class="rex-icon rex-icon-language" aria-hidden="true"></i>');
// cells of the language being added or edited
$formCells = static fn (string $form, string $code, string $name, int $priority, string $buttons): array => [
    'mark' => true,
    'icon' => $icon,
    'code' => Html::raw('<input class="rex-input" type="text" id="rex-form-language-code" name="language_code" value="' . escape($code) . '" required maxlength="35" autocapitalize="off" autocorrect="off" autofocus />'),
    'name' => Html::raw('<input class="rex-input" type="text" id="rex-form-language-name" name="language_name" value="' . escape($name) . '" required maxlength="255" />'),
    'priority' => Html::raw('<input class="rex-input" type="number" id="rex-form-language-prio" name="language_prio" value="' . $priority . '" required min="1" inputmode="numeric" />'),
    'edit' => Html::raw($buttons),
    'delete' => new TableCell(Html::raw('add' === $form
        ? '<button class="rex-button" data-variant="success" type="submit" name="add_language_save"' . Accesskey::attributes(I18n::msg('language_add'), 'save') . ' value="1">' . I18n::msg('language_add') . '</button>'
        : '<button class="rex-button" data-variant="success" type="submit" name="edit_language_save"' . Accesskey::attributes(I18n::msg('language_update'), 'save') . ' value="1">' . I18n::msg('language_update') . '</button>'), 2),
];

$rows = [];

if ('add' == $func) {
    $meta ??= new MetaForm(new MetaContext(MetaEntity::Language));

    // ----- EXTENSION POINT
    $rows[] = ['id' => '–'] + $formCells('add', $languageCode, $languageName, $languagePrio ?: Language::count() + 1, $metaToggle($meta) . Extension::dispatch(new LanguageFormButtons()));
    $rows[] = $metaRow($meta);
    // ----- EXTENSION POINT
    $rows[] = Html::raw(Extension::dispatch(new LanguageFormAdd()));
}

$sql = Sql::factory()->setQuery('SELECT * FROM rex_language ORDER BY priority');
foreach ($sql as $row) {
    $langId = $sql->getIntValue('id');

    if ('edit' == $func && $languageId == $langId) {
        $language = Language::require($langId);
        $meta ??= new MetaForm(new MetaContext(MetaEntity::Language, $language));

        // ----- EXTENSION POINT
        $rows[] = ['id' => $langId] + $formCells('edit', $sql->getStringValue('code'), $sql->getStringValue('name'), $sql->getIntValue('priority'), $metaToggle($meta) . Extension::dispatch(new LanguageFormButtons($language)));
        $rows[] = $metaRow($meta);
        // ----- EXTENSION POINT
        $rows[] = Html::raw(Extension::dispatch(new LanguageFormEdit($language)));
        continue;
    }

    $editLink = Url::currentBackendPage(['func' => 'edit', 'language_id' => $langId]) . '#rex-form-system-language';
    $status = $sql->getBoolValue('status') ? 'online' : 'offline';

    $rows[] = [
        'mark' => false,
        'icon' => Html::raw('<a class="rex-u-link-expanded" href="' . $editLink . '" title="' . escape($sql->getStringValue('name')) . '" tabindex="-1" aria-hidden="true"><i class="rex-icon rex-icon-language"></i></a>'),
        'id' => $langId,
        'code' => $sql->getStringValue('code'),
        'name' => $sql->getStringValue('name'),
        'priority' => $sql->getIntValue('priority'),
        'edit' => Html::raw('<a class="rex-u-link-expanded" href="' . $editLink . '"><i class="rex-icon rex-icon-edit" aria-hidden="true"></i> ' . I18n::msg('edit') . '</a>'),
        'delete' => Html::raw($langId == Language::getStartId()
            ? '<span class="rex-u-text-muted"><i class="rex-icon rex-icon-delete" aria-hidden="true"></i> ' . I18n::msg('delete') . '</span>'
            : '<a class="rex-u-link-expanded" href="' . Url::currentBackendPage(['func' => 'delete', 'language_id' => $langId] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete" aria-hidden="true"></i> ' . I18n::msg('delete') . '</a>'),
        'status' => Html::raw('<a class="rex-u-link-expanded rex-u-' . $status . '" href="' . Url::currentBackendPage(['language_id' => $langId, 'func' => 'editstatus', 'language_status' => $sql->getBoolValue('status') ? 0 : 1] + $csrfToken->getUrlParams()) . '"><i class="rex-icon rex-icon-' . $status . '" aria-hidden="true"></i> ' . I18n::msg('language_' . $status) . '</a>'),
    ];
}

$content .= (string) new Table(
    [
        TableColumn::forKey(Html::raw('<a class="rex-u-link-expanded" href="' . Url::currentBackendPage(['func' => 'add']) . '#language"' . Accesskey::attributes(I18n::msg('language_add'), 'add') . '><i class="rex-icon rex-icon-add-language" aria-hidden="true"></i><span class="rex-u-visually-hidden">' . I18n::msg('language_add') . '</span></a>'), 'icon', TableColumnType::Icon),
        TableColumn::forKey(I18n::rawMsg('id'), 'id', TableColumnType::Id),
        TableColumn::forKey(I18n::rawMsg('language_code'), 'code'),
        TableColumn::forKey(I18n::rawMsg('language_name'), 'name'),
        TableColumn::forKey(I18n::rawMsg('language_priority'), 'priority', TableColumnType::Priority),
        TableColumn::forKey(I18n::rawMsg('language_function'), 'edit', TableColumnType::Action),
        TableColumn::forKey(null, 'delete', TableColumnType::Action),
        TableColumn::forKey(null, 'status', TableColumnType::Action),
    ],
    array_filter($rows),
    rowAttributes: static fn (array $row): HtmlAttributes => new HtmlAttributes(['class' => ['rex-u-mark' => (bool) ($row['mark'] ?? false)]]),
    attributes: new HtmlAttributes(['data-hover' => true]),
)->render();

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
