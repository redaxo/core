<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\ApiFunction\DefaultFieldsCreate;
use Redaxo\Core\MetaInfo\Form\MetaInfoForm;
use Redaxo\Core\MetaInfo\MetaInfo;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

$content = '';

// ------------------------------> Parameter
/** @psalm-suppress TypeDoesNotContainType */
if (empty($prefix) || !is_string($prefix)) {
    throw new LogicException('Missing $prefix variable.');
}

if (empty($metaTable) || !is_string($metaTable)) {
    throw new LogicException('Missing $metaTable variable.');
}

$func = Request::request('func', 'string');
$fieldId = Request::request('field_id', 'int');

// ------------------------------> Feld loeschen
if ('delete' == $func) {
    $fieldId = Request::request('field_id', 'int', 0);
    if (0 != $fieldId) {
        if (MetaInfo::deleteField($fieldId)) {
            echo Message::success(I18n::msg('minfo_field_successfull_deleted'));
        } else {
            echo Message::error(I18n::msg('minfo_field_error_deleted'));
        }
    }
    $func = '';
}

// ------------------------------> Eintragsliste
if ('' == $func) {
    echo ApiFunction::getMessage();

    $title = I18n::msg('minfo_field_list_caption');

    $sql = Sql::factory();
    $likePrefix = $sql->escape($sql->escapeLikeWildcards($prefix) . '%');

    $list = DataList::factory('SELECT id, name, priority FROM ' . Core::getTablePrefix() . 'metainfo_field WHERE `name` LIKE ' . $likePrefix . ' ORDER BY priority');
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-metainfo"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"><i class="rex-icon rex-icon-add-metainfo"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'field_id' => '###id###']);

    $list->removeColumn('id');

    $list->setColumnLabel('id', I18n::msg('minfo_field_label_id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id" data-title="' . I18n::msg('minfo_field_label_id') . '">###VALUE###</td>']);

    $list->setColumnLabel('name', I18n::msg('minfo_field_label_name'));
    $list->setColumnParams('name', ['func' => 'edit', 'field_id' => '###id###']);

    $list->setColumnLabel('priority', I18n::msg('minfo_field_label_priority'));
    $list->setColumnLayout('priority', ['<th class="rex-table-priority">###VALUE###</th>', '<td class="rex-table-priority" data-title="' . I18n::msg('minfo_field_label_priority') . '">###VALUE###</td>']);

    $list->addColumn(I18n::msg('minfo_field_label_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('minfo_field_label_functions'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('minfo_field_label_functions'), ['func' => 'edit', 'field_id' => '###id###']);
    $list->addLinkAttribute(I18n::msg('minfo_field_label_functions'), 'class', 'rex-edit');

    $list->addColumn('delete', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout('delete', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('delete', ['func' => 'delete', 'field_id' => '###id###']);
    $list->addLinkAttribute('delete', 'data-confirm', I18n::msg('delete') . ' ?');
    $list->addLinkAttribute('delete', 'class', 'rex-delete');

    $list->setNoRowsMessage(I18n::msg('minfo_metainfos_not_found'));

    $content .= $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $title);

    if (in_array($prefix, ['art_', 'med_'])) {
        $defaultFields = sprintf(
            '<div class="btn-group btn-group-xs"><a href="%s" class="btn btn-default">%s</a></div>',
            Url::currentBackendPage(['type' => Controller::getCurrentPagePart(2)] + DefaultFieldsCreate::getUrlParams()),
            I18n::msg('minfo_default_fields_create'),
        );
        $fragment->setVar('options', $defaultFields, false);
    }

    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');
}
// ------------------------------> Formular
elseif ('edit' == $func || 'add' == $func) {
    $title = I18n::msg('minfo_field_fieldset');
    $form = new MetaInfoForm($prefix, $metaTable, Core::getTablePrefix() . 'metainfo_field', 'id=' . $fieldId);

    if ('edit' == $func) {
        $form->addParam('field_id', $fieldId);
    }

    $content .= $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $title);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');
}

echo $content;
