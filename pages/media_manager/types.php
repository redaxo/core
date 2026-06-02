<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$content = '';

$typeId = Request::request('type_id', 'int');
$func = Request::request('func', 'string');

if (Request::request('effects', 'boolean')) {
    include __DIR__ . '/effects.php';
    return;
}

$success = '';
$error = '';

// -------------- delete type
if ('delete' == $func && $typeId > 0) {
    // must be called before deletion, otherwise the method can not resolve the id to type name
    MediaManager::deleteCacheByType($typeId);

    $sql = Sql::factory();
    //  $sql->setDebug();

    $sql->transactional(static function () use ($sql, $typeId) {
        $sql->setTable(Core::getTablePrefix() . 'media_manager_type');
        $sql->setWhere(['id' => $typeId]);
        $sql->delete();

        $sql->setTable(Core::getTablePrefix() . 'media_manager_type_effect');
        $sql->setWhere(['type_id' => $typeId]);
        $sql->delete();
    });

    $success = I18n::msg('media_manager_type_deleted');
    $func = '';
}

// -------------- delete cache by type-id
if ('delete_cache' == $func && $typeId > 0) {
    $counter = MediaManager::deleteCacheByType($typeId);
    $success = I18n::msg('media_manager_cache_files_removed', $counter);
    $func = '';
}

// -------------- copy type
if ('copy' == $func && $typeId > 0) {
    $sql = Sql::factory();

    try {
        $sql->setQuery('INSERT INTO ' . Core::getTablePrefix() . 'media_manager_type (status, name, description) SELECT 0, CONCAT(name, \' ' . I18n::msg('media_manager_type_name_copy') . '\'), description FROM ' . Core::getTablePrefix() . 'media_manager_type WHERE id = ?', [$typeId]);
        $newTypeId = $sql->getLastId();
        $login = Core::requireUser()->login;
        $sql->setQuery('INSERT INTO ' . Core::getTablePrefix() . 'media_manager_type_effect (type_id, effect, parameters, priority, updatedate, updateuser, createdate, createuser) SELECT ?, effect, parameters, priority, ?, ?, ?, ? FROM ' . Core::getTablePrefix() . 'media_manager_type_effect WHERE type_id = ?', [$newTypeId, date(Sql::FORMAT_DATETIME), $login, date(Sql::FORMAT_DATETIME), $login, $typeId]);

        $success = I18n::msg('media_manager_type_copied');
    } catch (SqlException $e) {
        if (Sql::ERROR_VIOLATE_UNIQUE_KEY !== $e->getErrorCode()) {
            throw $e;
        }
        $error = $sql->getError();
    }

    $func = '';
}

// -------------- output messages
if ('' != $success) {
    echo Message::success($success);
}

if ('' != $error) {
    echo Message::error($error);
}

if ('' == $func) {
    // Nach Status sortieren, damit Systemtypen immer zuletzt stehen
    // (werden am seltesten bearbeitet)
    $query = 'SELECT id, status, name, description FROM ' . Core::getTablePrefix() . 'media_manager_type ORDER BY status, name';

    $list = DataList::factory($query, 100);
    $list->addTableAttribute('class', 'table-striped table-hover');
    $list->setNoRowsMessage(I18n::msg('media_manager_type_no_types'));

    $list->removeColumn('id');
    $list->removeColumn('status');
    $list->removeColumn('description');

    $list->setColumnLabel('name', I18n::msg('media_manager_type_name'));
    $list->setColumnFormat('name', 'custom', static function () use ($list) {
        $name = '<b>' . escape($list->getValue('name')) . '</b>';
        $name .= ('' != $list->getValue('description')) ? '<br /><span class="rex-note">' . escape($list->getValue('description')) . '</span>' : '';
        return $name;
    });

    // icon column
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '" title="' . I18n::msg('media_manager_type_create') . '"><i class="rex-icon rex-icon-add-mediatype"></i></a>';
    $list->addColumn($thIcon, '', 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'type_id' => '###id###']);
    $list->setColumnFormat($thIcon, 'custom', static function () use ($list, $thIcon) {
        $tdIcon = '<i class="rex-icon rex-icon-mediatype"></i>';
        if (MediaManager::STATUS_SYSTEM_TYPE == $list->getValue('status')) {
            return $tdIcon;
        }
        return $list->getColumnLink($thIcon, $tdIcon);
    });

    // functions column spans 5 data-columns
    $funcs = I18n::msg('media_manager_type_functions');

    $list->addColumn($funcs, '', -1, ['<th class="rex-table-action" colspan="5">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($funcs, ['type_id' => '###id###', 'effects' => 1]);
    $list->setColumnFormat($funcs, 'custom', static function () use ($list, $funcs) {
        if (MediaManager::STATUS_SYSTEM_TYPE == $list->getValue('status')) {
            return '<small class="text-muted">' . I18n::msg('media_manager_type_system') . '</small>';
        }
        return $list->getColumnLink($funcs, '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('media_manager_type_effekts_edit'));
    });

    $list->addColumn('deleteCache', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('media_manager_type_cache_delete'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('deleteCache', ['type_id' => '###id###', 'func' => 'delete_cache']);
    $list->addLinkAttribute('deleteCache', 'data-confirm', I18n::msg('media_manager_type_cache_delete') . ' ?');

    $list->addColumn('editType', '', -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('editType', ['func' => 'edit', 'type_id' => '###id###']);
    $list->setColumnFormat('editType', 'custom', static function () use ($list) {
        if (MediaManager::STATUS_SYSTEM_TYPE == $list->getValue('status')) {
            return '';
        }
        return $list->getColumnLink('editType', '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('media_manager_type_edit'));
    });

    $list->addColumn('copyType', '<i class="rex-icon rex-icon-duplicate"></i> ' . I18n::msg('media_manager_type_copy'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('copyType', ['func' => 'copy', 'type_id' => '###id###']);

    // override delete link on internal types
    $list->addColumn('deleteType', '', -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('deleteType', ['type_id' => '###id###', 'func' => 'delete']);
    $list->addLinkAttribute('deleteType', 'data-confirm', I18n::msg('delete') . ' ?');
    $list->setColumnFormat('deleteType', 'custom', static function () use ($list) {
        if (MediaManager::STATUS_SYSTEM_TYPE == $list->getValue('status')) {
            return '';
        }
        return $list->getColumnLink('deleteType', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('media_manager_type_delete'));
    });

    $content .= $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('media_manager_type_caption'), false);
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} elseif ('add' == $func || 'edit' == $func && $typeId > 0) {
    if ('edit' == $func) {
        $formLabel = I18n::msg('media_manager_type_edit');
    } else {
        $formLabel = I18n::msg('media_manager_type_create');
    }

    Extension::register('REX_FORM_CONTROL_FIELDS', static function (ExtensionPoint $ep) {
        $controlFields = $ep->subject;
        $form = $ep->getParam('form');
        $sql = $form->getSql();

        // remove delete button on internal types
        if ($sql->getRows() > 0 && MediaManager::STATUS_SYSTEM_TYPE == $sql->getValue('status')) {
            $controlFields['delete'] = '';
        }
        return $controlFields;
    });

    $form = Form::factory(Core::getTablePrefix() . 'media_manager_type', '', 'id = ' . $typeId);

    if ($typeId && MediaManager::STATUS_SYSTEM_TYPE === (int) $form->getSql()->getValue('status')) {
        throw new NotFoundHttpException('System media types can not be edited.');
    }

    $form->addParam('type_id', $typeId);
    if ('edit' == $func) {
        $form->setEditMode('edit' == $func);

        Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep) use ($form, $typeId) {
            if ($form !== $ep->getParam('form')) {
                return;
            }

            MediaManager::deleteCacheByType($typeId);
        });
    }

    $form->addErrorMessage(Form::ERROR_VIOLATE_UNIQUE_KEY, I18n::msg('media_manager_error_type_name_not_unique'));

    $field = $form->addTextField('name');
    $field->setLabel(I18n::msg('media_manager_type_name'));
    $field->disableSpellcheckAndAutoCorrect();
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, I18n::msg('media_manager_error_name'))
        ->add(ValidationRule::NOT_MATCH, I18n::msg('media_manager_error_type_name_invalid'), '{[/\\\\]}')
        ->add(ValidationRule::MAX_LENGTH, null, 255)
    ;

    // system mediatypes are not editable
    if ('edit' == $func) {
        if (MediaManager::STATUS_SYSTEM_TYPE == $form->getSql()->getValue('status')) {
            $field->setAttribute('readonly', 'readonly');
        }
    }

    $field = $form->addTextareaField('description');
    $field->setLabel(I18n::msg('media_manager_type_description'));
    $field->getValidator()->add(ValidationRule::MAX_LENGTH, null, 255);

    $content .= $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formLabel, false);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
