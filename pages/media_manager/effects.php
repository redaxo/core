<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaManager\Effect\AbstractEffect;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$effectId = Request::request('effect_id', 'int');
$typeId = Request::request('type_id', 'int');
$func = Request::request('func', 'string');

// ---- validate type_id
$sql = Sql::factory();
$sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media_manager_type WHERE id=' . $typeId);
if (1 != $sql->getRows()) {
    throw new NotFoundHttpException('Invalid type_id "' . $typeId . '"');
}
if (MediaManager::STATUS_SYSTEM_TYPE === (int) $sql->getValue('status')) {
    throw new NotFoundHttpException('System media types can not be edited.');
}
$typeName = (string) $sql->getValue('name');

$info = '';
$warning = '';

// -------------- delete effect
if ('delete' == $func && $effectId > 0) {
    $sql = Sql::factory();
    //  $sql->setDebug();
    $sql->setTable(Core::getTablePrefix() . 'media_manager_type_effect');
    $sql->setWhere(['id' => $effectId]);

    try {
        $sql->delete();

        Util::organizePriorities(
            Core::getTablePrefix() . 'media_manager_type_effect',
            'priority',
            'type_id = ' . $typeId,
            'priority, updatedate desc',
        );

        $info = I18n::msg('media_manager_effect_deleted');

        MediaManager::deleteCacheByType($typeId);

        Sql::factory()
            ->setTable(Core::getTable('media_manager_type'))
            ->setWhere(['id' => $typeId])
            ->addGlobalUpdateFields()
            ->update();
    } catch (SqlException) {
        $warning = $sql->getError();
    }
    $func = '';
}

if ('' != $info) {
    echo Message::info($info);
}

if ('' != $warning) {
    echo Message::warning($warning);
}

$effects = [];
foreach (MediaManager::getSupportedEffects() as $class => $shortName) {
    $class = new $class();
    $effects[$class::class] = $class;
}

if ('' == $func) {
    echo Message::info(I18n::msg('media_manager_effect_list_header', $typeName));

    $query = 'SELECT * FROM ' . Core::getTablePrefix() . 'media_manager_type_effect WHERE type_id=' . $typeId . ' ORDER BY priority';

    $list = DataList::factory($query, 100);
    $list->addTableAttribute('class', 'table-striped table-hover');
    $list->addParam('effects', 1);

    $list->setNoRowsMessage(I18n::msg('media_manager_effect_no_effects'));

    $list->removeColumn('id');
    $list->removeColumn('type_id');
    $list->removeColumn('parameters');
    $list->removeColumn('updatedate');
    $list->removeColumn('updateuser');
    $list->removeColumn('createdate');
    $list->removeColumn('createuser');

    $list->setColumnLabel('effect', I18n::msg('media_manager_type_name'));
    $list->setColumnFormat('effect', 'custom', static function ($params) use ($effects) {
        $shortName = $params['value'];
        return isset($effects[$shortName]) ? $effects[$shortName]->getName() : $shortName;
    });

    $list->setColumnLabel('priority', I18n::msg('media_manager_type_priority'));
    $list->setColumnLayout('priority', ['<th class="rex-table-priority">###VALUE###</th>', '<td class="rex-table-priority">###VALUE###</td>']);

    // icon column
    $thIcon = '<a href="' . $list->getUrl(['type_id' => $typeId, 'func' => 'add']) . '" title="' . I18n::msg('media_manager_effect_create') . '"><i class="rex-icon rex-icon-add-mediatype-effect"></i></a>';
    $tdIcon = '<i class="rex-icon rex-icon-mediatype-effect"></i>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'type_id' => $typeId, 'effect_id' => '###id###']);

    // functions column spans 2 data-columns
    $funcs = I18n::msg('media_manager_effect_functions');
    $list->addColumn($funcs, '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('media_manager_effect_edit'), -1, ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($funcs, ['func' => 'edit', 'type_id' => $typeId, 'effect_id' => '###id###']);

    $delete = 'deleteCol';
    $list->addColumn($delete, '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('media_manager_effect_delete'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($delete, ['type_id' => $typeId, 'effect_id' => '###id###', 'func' => 'delete']);
    $list->addLinkAttribute($delete, 'data-confirm', I18n::msg('delete') . ' ?');

    $content = $list->get();

    $footer = '<a class="btn btn-back" href="' . Url::currentBackendPage() . '">' . I18n::msg('media_manager_back') . '</a>';

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::rawMsg('media_manager_effect_caption', escape($typeName)), false);
    $fragment->setVar('content', $content, false);
    $fragment->setVar('footer', $footer, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} elseif ('add' == $func || 'edit' == $func && $effectId > 0) {
    uasort($effects, static function (AbstractEffect $a, AbstractEffect $b) {
        return strnatcmp($a->getName(), $b->getName());
    });

    if ('edit' == $func) {
        $formLabel = I18n::rawMsg('media_manager_effect_edit_header', escape($typeName));
    } else {
        $formLabel = I18n::rawMsg('media_manager_effect_create_header', escape($typeName));
    }

    $form = Form::factory(Core::getTablePrefix() . 'media_manager_type_effect', '', 'id=' . $effectId);

    // image_type_id for reference to save into the db
    $form->addHiddenField('type_id', $typeId);

    // effect prio
    $field = $form->addPrioField('priority');
    $field->setLabel(I18n::msg('media_manager_effect_priority'));
    $field->setAttribute('class', 'selectpicker form-control');
    $field->setLabelField('effect');
    $field->setLabelCallback(static function ($shortName) use ($effects) {
        return isset($effects[$shortName]) ? $effects[$shortName]->getName() : $shortName;
    });
    $field->setWhereCondition('type_id = ' . $typeId);

    // effect name als SELECT
    $field = $form->addSelectField('effect');
    $field->setLabel(I18n::msg('media_manager_effect_name'));
    $field->setAttribute('class', 'selectpicker form-control');
    $field->setAttribute('data-live-search', 'true');
    $select = $field->getSelect();
    foreach ($effects as $name => $effect) {
        $select->addOption($effect->getName(), $name);
    }
    $select->setSize(1);

    $script = '
    <script type="text/javascript" nonce="' . Response::getNonce() . '">
    <!--

    (function($) {
        var currentShown = null;
        $("#' . $field->getAttribute('id') . '").change(function(){
            if(currentShown) currentShown.hide();

            let effectParamsId = "#rex-" + jQuery(this).val().toLowerCase().replace(/\\\/g,"_");
            console.log(effectParamsId);
            currentShown = $(effectParamsId);
            currentShown.show();
        }).change();
    })(jQuery);

    //--></script>';

    // effect parameters
    $fieldContainer = $form->addContainerField('parameters');
    $fieldContainer->setAttribute('style', 'display: none');
    $fieldContainer->setSuffix($script);

    foreach ($effects as $effect => $effectObj) {
        $effectClass = $effectObj::class;
        $effectParams = $effectObj->getParams();
        $group = Str::normalize($effect, '_');

        if (empty($effectParams)) {
            continue;
        }

        foreach ($effectParams as $param) {
            $name = Str::normalize($effectClass . '-' . $param['name']);
            /** @psalm-suppress MixedAssignment */
            $value = $param['default'] ?? null;
            $attributes = [];
            if (isset($param['attributes'])) {
                $attributes = $param['attributes'];
            }

            switch ($param['type']) {
                case 'int':
                case 'float':
                case 'string':
                    $type = 'text';
                    $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                    $field->setLabel($param['label']);
                    $field->setAttribute('id', "rex $name $type");
                    if (!empty($param['notice'])) {
                        $field->setNotice($param['notice']);
                    }
                    if (!empty($param['prefix'])) {
                        $field->setPrefix($param['prefix']);
                    }
                    if (!empty($param['suffix'])) {
                        $field->setSuffix($param['suffix']);
                    }
                    break;
                case 'select':
                    $type = $param['type'];
                    /** @var SelectField $field */
                    $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                    $field->setLabel($param['label']);
                    $field->setAttribute('id', "rex $name $type");
                    $field->setAttribute('class', 'form-control selectpicker');
                    if (!empty($param['notice'])) {
                        $field->setNotice($param['notice']);
                    }
                    if (!empty($param['prefix'])) {
                        $field->setPrefix($param['prefix']);
                    }
                    if (!empty($param['suffix'])) {
                        $field->setSuffix($param['suffix']);
                    }

                    $select = $field->getSelect();
                    if (!isset($attributes['multiple'])) {
                        $select->setSize(1);
                    }
                    $select->addOptions($param['options'] ?? [], true);
                    break;
                case 'media':
                    $type = $param['type'];
                    $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                    $field->setLabel($param['label']);
                    $field->setAttribute('id', "rex $name $type");
                    if (!empty($param['notice'])) {
                        $field->setNotice($param['notice']);
                    }
                    if (!empty($param['prefix'])) {
                        $field->setPrefix($param['prefix']);
                    }
                    if (!empty($param['suffix'])) {
                        $field->setSuffix($param['suffix']);
                    }
                    break;
                default:
                    throw new LogicException('Unexpected param type "' . $param['type'] . '".');
            }
        }
    }

    // parameters for url redirects
    $form->addParam('type_id', $typeId);
    $form->addParam('effects', 1);
    if ('edit' == $func) {
        $form->addParam('effect_id', $effectId);
    }

    Extension::register('REX_FORM_SAVED', static function (ExtensionPoint $ep) use ($form, $typeId) {
        if ($form !== $ep->getParam('form')) {
            return;
        }

        MediaManager::deleteCacheByType($typeId);

        Sql::factory()
            ->setTable(Core::getTable('media_manager_type'))
            ->setWhere(['id' => $typeId])
            ->addGlobalUpdateFields()
            ->update();
    });

    $content = $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formLabel, false);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
