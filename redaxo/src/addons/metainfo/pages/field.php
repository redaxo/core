<?php

/**
 * MetaForm Addon.
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 */

$content = '';

// ------------------------------> Parameter
/** @psalm-suppress TypeDoesNotContainType */
if (empty($prefix) || !is_string($prefix)) {
    throw new rex_exception('Fehler: Prefix nicht definiert!');
}

if (empty($metaTable) || !is_string($metaTable)) {
    throw new rex_exception('Fehler: metaTable nicht definiert!');
}

$func = rex_request('func', 'string');
$fieldId = rex_request('field_id', 'int');

$csrfToken = rex_csrf_token::factory('metainfo_field');

// ------------------------------> Feld loeschen
if ('delete' == $func && !$csrfToken->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    $func = '';
} elseif ('delete' == $func) {
    $fieldId = rex_request('field_id', 'int', 0);
    if (0 != $fieldId) {
        if (rex_metainfo_delete_field($fieldId)) {
            echo rex_view::success(rex_i18n::msg('minfo_field_successfull_deleted'));
        } else {
            echo rex_view::error(rex_i18n::msg('minfo_field_error_deleted'));
        }
    }
    $func = '';
}

// ------------------------------> Eintragsliste
if ('' == $func) {
    echo rex_api_function::getMessage();

    $title = rex_i18n::msg('minfo_field_list_caption');

    $sql = rex_sql::factory();
    $likePrefix = $sql->escape($sql->escapeLikeWildcards($prefix) . '%');

    $list = rex_list::factory('SELECT id, name, priority FROM ' . rex::getTablePrefix() . 'metainfo_field WHERE `name` LIKE ' . $likePrefix . ' ORDER BY priority');
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-metainfo"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"><i class="rex-icon rex-icon-add-metainfo"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'field_id' => '###id###']);

    $list->removeColumn('id');

    $list->setColumnLabel('id', rex_i18n::msg('minfo_field_label_id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id" data-title="' . rex_i18n::msg('minfo_field_label_id') . '">###VALUE###</td>']);

    $list->setColumnLabel('name', rex_i18n::msg('minfo_field_label_name'));
    $list->setColumnParams('name', ['func' => 'edit', 'field_id' => '###id###']);

    $list->setColumnLabel('priority', rex_i18n::msg('minfo_field_label_priority'));
    $list->setColumnLayout('priority', ['<th class="rex-table-priority">###VALUE###</th>', '<td class="rex-table-priority" data-title="' . rex_i18n::msg('minfo_field_label_priority') . '">###VALUE###</td>']);

    $list->addColumn(rex_i18n::msg('minfo_field_label_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit'));
    $list->setColumnLayout(rex_i18n::msg('minfo_field_label_functions'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(rex_i18n::msg('minfo_field_label_functions'), ['func' => 'edit', 'field_id' => '###id###']);
    $list->addLinkAttribute(rex_i18n::msg('minfo_field_label_functions'), 'class', 'rex-edit');

    $list->addColumn('delete', '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete'));
    $list->setColumnLayout('delete', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('delete', ['func' => 'delete', 'field_id' => '###id###'] + $csrfToken->getUrlParams());
    $list->addLinkAttribute('delete', 'data-confirm', rex_i18n::msg('delete') . ' ?');
    $list->addLinkAttribute('delete', 'class', 'rex-delete');

    $list->setNoRowsMessage(rex_i18n::msg('minfo_metainfos_not_found'));

    $content .= $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('title', $title);

    if (in_array($prefix, ['art_', 'med_'])) {
        $defaultFields = sprintf(
            '<div class="btn-group btn-group-xs"><a href="%s" class="btn btn-default">%s</a></div>',
            rex_url::currentBackendPage(['type' => rex_be_controller::getCurrentPagePart(2)] + rex_api_metainfo_default_fields_create::getUrlParams()),
            rex_i18n::msg('minfo_default_fields_create'),
        );
        $fragment->setVar('options', $defaultFields, false);
    }

    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');
}
// ------------------------------> Formular
elseif ('edit' == $func || 'add' == $func) {
    $title = rex_i18n::msg('minfo_field_fieldset');
    $form = new rex_metainfo_table_expander($prefix, $metaTable, rex::getTablePrefix() . 'metainfo_field', 'id=' . $fieldId);

    if ('edit' == $func) {
        $form->addParam('field_id', $fieldId);
    }

    $content .= $form->get();

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $title);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');
}

echo $content;
