<?php

$effectId = rex_request('effect_id', 'int');
$typeId = rex_request('type_id', 'int');
$func = rex_request('func', 'string');

// ---- validate type_id
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'media_manager_type WHERE id=' . $typeId);
if (1 != $sql->getRows()) {
    throw new Exception('Invalid type_id "' . $typeId . '"');
}
if (rex_media_manager::STATUS_SYSTEM_TYPE === (int) $sql->getValue('status')) {
    throw new rex_exception('System media types can not be edited.');
}
$typeName = (string) $sql->getValue('name');

$info = '';
$warning = '';

// -------------- delete effect
if ('delete' == $func && $effectId > 0) {
    $sql = rex_sql::factory();
    //  $sql->setDebug();
    $sql->setTable(rex::getTablePrefix() . 'media_manager_type_effect');
    $sql->setWhere(['id' => $effectId]);

    try {
        $sql->delete();

        rex_sql_util::organizePriorities(
            rex::getTablePrefix() . 'media_manager_type_effect',
            'priority',
            'type_id = ' . $typeId,
            'priority, updatedate desc',
        );

        $info = rex_i18n::msg('media_manager_effect_deleted');

        rex_media_manager::deleteCacheByType($typeId);

        rex_sql::factory()
            ->setTable(rex::getTable('media_manager_type'))
            ->setWhere(['id' => $typeId])
            ->addGlobalUpdateFields()
            ->update();
    } catch (rex_sql_exception) {
        $warning = $sql->getError();
    }
    $func = '';
}

if ('' != $info) {
    echo rex_view::info($info);
}

if ('' != $warning) {
    echo rex_view::warning($warning);
}

$effects = [];
foreach (rex_media_manager::getSupportedEffects() as $class => $shortName) {
    $effects[$shortName] = new $class();
}

if ('' == $func) {
    echo rex_view::info(rex_i18n::msg('media_manager_effect_list_header', $typeName));

    $query = 'SELECT * FROM ' . rex::getTablePrefix() . 'media_manager_type_effect WHERE type_id=' . $typeId . ' ORDER BY priority';

    $list = rex_list::factory($query, 100);
    $list->addTableAttribute('class', 'table-striped table-hover');
    $list->addParam('effects', 1);

    $list->setNoRowsMessage(rex_i18n::msg('media_manager_effect_no_effects'));

    $list->removeColumn('id');
    $list->removeColumn('type_id');
    $list->removeColumn('parameters');
    $list->removeColumn('updatedate');
    $list->removeColumn('updateuser');
    $list->removeColumn('createdate');
    $list->removeColumn('createuser');

    $list->setColumnLabel('effect', rex_i18n::msg('media_manager_type_name'));
    $list->setColumnFormat('effect', 'custom', static function ($params) use ($effects) {
        $shortName = $params['value'];
        return isset($effects[$shortName]) ? $effects[$shortName]->getName() : rex_escape((string) $shortName);
    });

    $list->setColumnLabel('priority', rex_i18n::msg('media_manager_type_priority'));
    $list->setColumnLayout('priority', ['<th class="rex-table-priority">###VALUE###</th>', '<td class="rex-table-priority">###VALUE###</td>']);

    // icon column
    $thIcon = '<a href="' . $list->getUrl(['type_id' => $typeId, 'func' => 'add']) . '" title="' . rex_i18n::msg('media_manager_effect_create') . '"><i class="rex-icon rex-icon-add-mediatype-effect"></i></a>';
    $tdIcon = '<i class="rex-icon rex-icon-mediatype-effect"></i>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'type_id' => $typeId, 'effect_id' => '###id###']);

    // functions column spans 2 data-columns
    $funcs = rex_i18n::msg('media_manager_effect_functions');
    $list->addColumn($funcs, '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('media_manager_effect_edit'), -1, ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($funcs, ['func' => 'edit', 'type_id' => $typeId, 'effect_id' => '###id###']);

    $delete = 'deleteCol';
    $list->addColumn($delete, '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('media_manager_effect_delete'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($delete, ['type_id' => $typeId, 'effect_id' => '###id###', 'func' => 'delete']);
    $list->addLinkAttribute($delete, 'data-confirm', rex_i18n::msg('delete') . ' ?');

    $content = $list->get();

    $footer = '<a class="btn btn-back" href="' . rex_url::currentBackendPage() . '">' . rex_i18n::msg('media_manager_back') . '</a>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::rawMsg('media_manager_effect_caption', rex_escape($typeName)), false);
    $fragment->setVar('content', $content, false);
    $fragment->setVar('footer', $footer, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} elseif ('add' == $func || 'edit' == $func && $effectId > 0) {
    uasort($effects, static function (rex_effect_abstract $a, rex_effect_abstract $b) {
        return strnatcmp($a->getName(), $b->getName());
    });

    if ('edit' == $func) {
        $formLabel = rex_i18n::rawMsg('media_manager_effect_edit_header', rex_escape($typeName));
    } else {
        $formLabel = rex_i18n::rawMsg('media_manager_effect_create_header', rex_escape($typeName));
    }

    $form = rex_form::factory(rex::getTablePrefix() . 'media_manager_type_effect', '', 'id=' . $effectId);

    // image_type_id for reference to save into the db
    $form->addHiddenField('type_id', $typeId);

    // effect prio
    $field = $form->addPrioField('priority');
    $field->setLabel(rex_i18n::msg('media_manager_effect_priority'));
    $field->setAttribute('class', 'selectpicker form-control');
    $field->setLabelField('effect');
    $field->setLabelCallback(static function ($shortName) use ($effects) {
        return isset($effects[$shortName]) ? $effects[$shortName]->getName() : $shortName;
    });
    $field->setWhereCondition('type_id = ' . $typeId);

    // effect name als SELECT
    $field = $form->addSelectField('effect');
    $field->setLabel(rex_i18n::msg('media_manager_effect_name'));
    $field->setAttribute('class', 'selectpicker form-control');
    $field->setAttribute('data-live-search', 'true');
    $select = $field->getSelect();
    foreach ($effects as $name => $effect) {
        $select->addOption($effect->getName(), $name);
    }
    $select->setSize(1);

    $script = '
    <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
    <!--

    (function($) {
        var currentShown = null;
        $("#' . $field->getAttribute('id') . '").change(function(){
            if(currentShown) currentShown.hide();

            var effectParamsId = "#rex-rex_effect_"+ jQuery(this).val();
            currentShown = $(effectParamsId);
            currentShown.show();
        }).change();
    })(jQuery);

    //--></script>';

    // effect parameters
    $fieldContainer = $form->addContainerField('parameters');
    $fieldContainer->setAttribute('style', 'display: none');
    $fieldContainer->setSuffix($script);

    foreach ($effects as $effectObj) {
        $effectClass = $effectObj::class;
        $effectParams = $effectObj->getParams();
        $group = $effectClass;

        if (empty($effectParams)) {
            continue;
        }

        foreach ($effectParams as $param) {
            $name = $effectClass . '_' . $param['name'];
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
                    $field->setAttribute('id', "media_manager $name $type");
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
                    /** @var rex_form_select_element $field */
                    $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                    $field->setLabel($param['label']);
                    $field->setAttribute('id', "media_manager $name $type");
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
                    $field->setAttribute('id', "media_manager $name $type");
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
                    throw new rex_exception('Unexpected param type "' . $param['type'] . '"');
            }
        }
    }

    // parameters for url redirects
    $form->addParam('type_id', $typeId);
    $form->addParam('effects', 1);
    if ('edit' == $func) {
        $form->addParam('effect_id', $effectId);
    }

    rex_extension::register('REX_FORM_SAVED', static function (rex_extension_point $ep) use ($form, $typeId) {
        if ($form !== $ep->getParam('form')) {
            return;
        }

        rex_media_manager::deleteCacheByType($typeId);

        rex_sql::factory()
            ->setTable(rex::getTable('media_manager_type'))
            ->setWhere(['id' => $typeId])
            ->addGlobalUpdateFields()
            ->update();
    });

    $content = $form->get();

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formLabel, false);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
