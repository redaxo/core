<?php

use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\CronjobExecutor;
use Redaxo\Core\Cronjob\CronjobManager;
use Redaxo\Core\Cronjob\Form\CronjobForm;
use Redaxo\Core\Cronjob\Type\AbstractType;
use Redaxo\Core\Cronjob\Type\UrlRequestType;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\RadioField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$oid = Request::request('oid', 'int');

$csrfToken = CsrfToken::factory('cronjob');

if (in_array($func, ['setstatus', 'delete', 'execute']) && !$csrfToken->isValid()) {
    echo Message::error(I18n::msg('csrf_token_invalid'));
    $func = '';
} elseif ('setstatus' == $func) {
    $manager = CronjobManager::factory();
    $name = $manager->getName($oid);
    $status = (Request::request('oldstatus', 'int') + 1) % 2;
    $msg = 1 == $status ? 'status_activate' : 'status_deactivate';
    if ($manager->setStatus($oid, $status)) {
        echo Message::success(I18n::msg('cronjob_' . $msg . '_success', $name));
    } else {
        echo Message::error(I18n::msg('cronjob_' . $msg . '_error', $name));
    }
    $func = '';
} elseif ('delete' == $func) {
    $manager = CronjobManager::factory();
    $name = $manager->getName($oid);
    if ($manager->delete($oid)) {
        echo Message::success(I18n::msg('cronjob_delete_success', $name));
    } else {
        echo Message::error(I18n::msg('cronjob_delete_error', $name));
    }
    $func = '';
} elseif ('execute' == $func) {
    $manager = CronjobManager::factory();
    $name = $manager->getName($oid);
    $success = $manager->tryExecute($oid);
    $msg = '';
    if ($manager->hasMessage()) {
        $msg = '<br /><br />' . I18n::msg('cronjob_log_message') . ': <br />' . nl2br(escape($manager->getMessage()));
    }
    if ($success) {
        echo Message::success(I18n::msg('cronjob_execute_success', $name) . $msg);
    } else {
        echo Message::error(I18n::msg('cronjob_execute_error', $name) . $msg);
    }
    $func = '';
}

if ('' == $func) {
    $query = 'SELECT id, name, type, environment, execution_moment, nexttime, status FROM ' . Core::getTable('cronjob') . ' ORDER BY name';

    $list = DataList::factory($query, 30, 'cronjobs');
    $list->addTableAttribute('class', 'table-striped table-hover');

    $list->setNoRowsMessage(I18n::msg('cronjob_no_cronjobs'));

    $tdIcon = '<i class="rex-icon rex-icon-cronjob"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '" title="' . I18n::msg('cronjob_add') . '"><i class="rex-icon rex-icon-add-cronjob"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'oid' => '###id###']);

    $list->removeColumn('id');
    $list->removeColumn('type');

    $list->setColumnLabel('name', I18n::msg('cronjob_name'));
    $list->setColumnParams('name', ['func' => 'edit', 'oid' => '###id###']);

    $list->setColumnLabel('environment', I18n::msg('cronjob_environment'));
    $list->setColumnFormat('environment', 'custom', static function () use ($list) {
        $value = $list->getValue('environment');
        $env = [];
        if (str_contains($value, '|frontend|')) {
            $env[] = I18n::msg('cronjob_environment_frontend');
        }
        if (str_contains($value, '|backend|')) {
            $env[] = I18n::msg('cronjob_environment_backend');
        }
        if (str_contains($value, '|script|')) {
            $env[] = I18n::msg('cronjob_environment_script');
        }
        return implode(', ', $env);
    });

    $list->setColumnLabel('execution_moment', I18n::msg('cronjob_execution'));
    $list->setColumnFormat('execution_moment', 'custom', static function () use ($list) {
        if ($list->getValue('execution_moment')) {
            return I18n::msg('cronjob_execution_beginning');
        }
        return I18n::msg('cronjob_execution_ending');
    });

    $list->setColumnLabel('nexttime', I18n::msg('cronjob_nexttime'));
    $list->setColumnFormat('nexttime', 'intlDateTime');

    $list->setColumnLabel('status', I18n::msg('cronjob_status_function'));
    $list->setColumnParams('status', ['func' => 'setstatus', 'oldstatus' => '###status###', 'oid' => '###id###'] + $csrfToken->getUrlParams());
    $list->setColumnLayout('status', ['<th class="rex-table-action" colspan="4">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnFormat('status', 'custom', static function () use ($list) {
        if (!class_exists($list->getValue('type')) || !in_array($list->getValue('type'), CronjobExecutor::getTypes())) {
            $str = I18n::msg('cronjob_status_invalid');
        } elseif (1 == $list->getValue('status')) {
            $str = $list->getColumnLink('status', '<span class="rex-online"><i class="rex-icon rex-icon-active-true"></i> ' . I18n::msg('cronjob_status_activated') . '</span>');
        } else {
            $str = $list->getColumnLink('status', '<span class="rex-offline"><i class="rex-icon rex-icon-active-false"></i> ' . I18n::msg('cronjob_status_deactivated') . '</span>');
        }
        return $str;
    });

    $list->addColumn('edit', '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('edit', ['func' => 'edit', 'oid' => '###id###']);

    $list->addColumn('delete', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('delete', ['func' => 'delete', 'oid' => '###id###'] + $csrfToken->getUrlParams());
    $list->addLinkAttribute('delete', 'data-confirm', I18n::msg('cronjob_really_delete'));

    $list->addColumn('execute', '<i class="rex-icon rex-icon-execute"></i> ' . I18n::msg('cronjob_execute'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('execute', ['func' => 'execute', 'oid' => '###id###'] + $csrfToken->getUrlParams());
    $list->addLinkAttribute('execute', 'data-pjax', 'false');
    $list->setColumnFormat('execute', 'custom', static function () use ($list) {
        if (str_contains($list->getValue('environment'), '|backend|') && class_exists($list->getValue('type'))) {
            return $list->getColumnLink('execute', '<i class="rex-icon rex-icon-execute"></i> ' . I18n::msg('cronjob_execute'));
        }
        return '<span class="text-muted"><i class="rex-icon rex-icon-execute"></i> ' . I18n::msg('cronjob_execute') . '</span>';
    });

    $content = $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('cronjob_caption'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
} elseif ('edit' == $func || 'add' == $func) {
    $fieldset = 'edit' == $func ? I18n::msg('edit') : I18n::msg('add');

    $form = new CronjobForm(Core::getTable('cronjob'), $fieldset, 'id = ' . $oid, 'post', false);
    $form->addParam('oid', $oid);
    $form->setEditMode('edit' == $func);

    $form->addHiddenField('nexttime');

    $field = $form->addTextField('name');
    $field->setLabel(I18n::msg('cronjob_name'));
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, I18n::msg('cronjob_error_no_name'))
        ->add(ValidationRule::MAX_LENGTH, null, 255)
    ;

    $field = $form->addTextAreaField('description');
    $field->setLabel(I18n::msg('description'));
    $field->getValidator()->add(ValidationRule::MAX_LENGTH, null, 255);

    $field = $form->addCheckboxField('environment');
    $field->setLabel(I18n::msg('cronjob_environment'));
    $field->setNotice(I18n::msg('cronjob_environment_notice', Path::bin('console') . ' cronjob:run'));
    $field->getValidator()->add('notEmpty', I18n::msg('cronjob_error_no_environment'));
    $envFieldId = escape($field->getAttribute('id'), 'js');
    $field->addOption(I18n::msg('cronjob_environment_frontend'), 'frontend');
    $field->addOption(I18n::msg('cronjob_environment_backend'), 'backend');
    $field->addOption(I18n::msg('cronjob_environment_script'), 'script');

    $field = $form->addRadioField('execution_moment');
    $field->setLabel(I18n::msg('cronjob_execution'));
    $field->addOption(I18n::msg('cronjob_execution_beginning'), 1);
    $field->addOption(I18n::msg('cronjob_execution_ending'), 0);
    if ('add' == $func) {
        $field->setValue(0);
    }

    $field = $form->addRadioField('status');
    $field->setLabel(I18n::msg('status'));
    $field->addOption(I18n::msg('cronjob_status_activated'), 1);
    $field->addOption(I18n::msg('cronjob_status_deactivated'), 0);
    if ('add' == $func) {
        $field->setValue(1);
    }

    $field = $form->addSelectField('type');
    $field->setAttribute('class', 'form-control selectpicker');
    $field->setLabel(I18n::msg('cronjob_type'));
    $select = $field->getSelect();
    $select->setSize(1);
    $typeFieldId = escape($field->getAttribute('id'), 'js');
    $types = CronjobExecutor::getTypes();
    $cronjobs = [];
    foreach ($types as $class) {
        $cronjob = AbstractType::factory($class);
        if ($cronjob instanceof AbstractType) {
            $cronjobs[$cronjob->getTypeName() . $class] = $cronjob;
        }
    }
    ksort($cronjobs);
    foreach ($cronjobs as $cronjob) {
        $class = $cronjob::class;
        $select->addOption($cronjob->getTypeName(), $class, 0, 0, ['data-cronjob_id' => Str::normalize($class)]);
    }
    if ('add' == $func) {
        $select->setSelected(UrlRequestType::class);
    }
    $activeType = $field->getValue();

    if ('add' != $func && !in_array($activeType, $types)) {
        if (!$activeType && !$field->getValue()) {
            $warning = I18n::rawMsg('cronjob_not_found');
        } else {
            $warning = I18n::rawMsg('cronjob_type_not_found', $field->getValue(), $activeType);
        }
        Response::sendRedirect(Url::currentBackendPage([Request::request('list', 'string') . '_warning' => $warning]));
    }

    $form->addFieldset(I18n::msg('cronjob_type_parameters'));

    $fieldContainer = $form->addContainerField('parameters');
    $fieldContainer->setAttribute('style', 'display: none');
    $fieldContainer->setMultiple(false);
    if ($activeType) {
        $fieldContainer->setActive(Str::normalize($activeType));
    }

    $form->addFieldset(I18n::msg('cronjob_interval'));
    $field = $form->addIntervalField('interval');
    $field->getValidator()->add('custom', I18n::msg('cronjob_error_interval_incomplete'), static function (string $interval) {
        /** @psalm-suppress MixedAssignment */
        foreach (json_decode($interval) as $value) {
            if ([] === $value) {
                return false;
            }
        }

        return true;
    });

    $envJs = '';
    $visible = [];
    foreach ($cronjobs as $cronjob) {
        $group = Str::normalize($cronjob::class);

        $disabled = array_diff(['frontend', 'backend', 'script'], (array) $cronjob->getEnvironments());
        if (count($disabled) > 0) {
            $envJs .= '
                if ($("#' . $typeFieldId . ' option:selected").val() == "' . escape($group, 'js') . '")
                    $("#' . $envFieldId . '-' . implode(', #' . $envFieldId . '-', $disabled) . '").prop("disabled","disabled").prop("checked",false);
';
        }

        $params = $cronjob->getParamFields();

        if (empty($params)) {
            $field = $fieldContainer->addGroupedField($group, 'readonly', 'noparams', I18n::msg('cronjob_type_no_parameters'));
            $field->setLabel('&nbsp;');
        } else {
            foreach ($params as $param) {
                $type = $param['type'];
                $name = $group . '_' . $param['name'];
                $label = !empty($param['label']) ? $param['label'] : '&nbsp;';
                $value = $param['default'] ?? null;
                $value = $param['value'] ?? $value;
                $attributes = $param['attributes'] ?? [];
                switch ($param['type']) {
                    case 'text':
                    case 'textarea':
                    case 'media':
                    case 'article':
                    case 'readonly':
                    case 'readonlytext':
                        $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                        $field->setLabel($label);
                        if (isset($param['notice'])) {
                            $field->setNotice($param['notice']);
                        }
                        break;
                    case 'select':
                        /** @var SelectField $field */
                        $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                        $field->setLabel($label);
                        $field->setAttribute('class', 'form-control selectpicker');
                        $select = $field->getSelect();
                        $select->addArrayOptions($param['options']);
                        if (isset($param['notice'])) {
                            $field->setNotice($param['notice']);
                        }
                        break;
                    case 'checkbox':
                    case 'radio':
                        /** @var RadioField $field */
                        $field = $fieldContainer->addGroupedField($group, $type, $name, $value, $attributes);
                        $field->addArrayOptions($param['options']);
                        if (isset($param['notice'])) {
                            $field->setNotice($param['notice']);
                        }
                        break;
                    default:
                        throw new LogicException(sprintf('Unexpected param type "%s".', $param['type']));
                }
                if (isset($param['visible_if']) && is_array($param['visible_if'])) {
                    foreach ($param['visible_if'] as $key => $value) {
                        $key = $group . '_' . $key;
                        if (!isset($visible[$key])) {
                            $visible[$key] = [];
                        }
                        if (!isset($visible[$key][$value])) {
                            $visible[$key][$value] = [];
                        }
                        $visible[$key][$value][] = $field->getAttribute('id');
                    }
                }
            }
        }
    }
    $visibleJs = '';
    if (!empty($visible)) {
        foreach ($fieldContainer->getFields() as $fieldElements) {
            foreach ($fieldElements as $field) {
                $name = $field->getFieldName();
                if (isset($visible[$name])) {
                    foreach ($visible[$name] as $value => $fieldIds) {
                        $visibleJs .= '
                        var first = 1;
                        $("#' . escape($field->getAttribute('id'), 'js') . '-' . escape($value, 'js') . '").change(function(){
                            var checkbox = $(this);
                            $("#' . escape(implode(',#', $fieldIds), 'js') . '").each(function(){
                                if ($(checkbox).is(":checked"))
                                    $(this).parent().parent().slideDown();
                                else if(first == 1)
                                    $(this).parent().parent().hide();
                                else
                                    $(this).parent().parent().slideUp();
                            });
                            first = 0;
                        }).change();';
                    }
                }
            }
        }
    }

    $content = $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $fieldset);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
?>

    <script type="text/javascript" nonce="<?= Response::getNonce() ?>">
    // <![CDATA[
        jQuery(function($){
            var currentShown = null;
            $("#<?= $typeFieldId ?>").change(function(){

                var cronjob_id = $(this).find('option:selected').data('cronjob_id');

                var next = $("#rex-"+ cronjob_id);

                if (next.is(currentShown)) {
                    return;
                }

                if (currentShown) {
                    currentShown.slideUp();
                    next.slideDown();
                } else {
                    next.show();
                }
                currentShown = next;
            }).change();
            $('#<?= $typeFieldId ?>').change(function(){
                $('#<?= $envFieldId ?> option').prop('disabled','');<?= $envJs ?>
            }).change();<?= $visibleJs . "\n" ?>
        });
    // ]]>
    </script>

    <style nonce="<?= Response::getNonce() ?>">
        .rex-cronjob-interval-all .checkbox label {
            font-weight: 700;
        }

        .rex-cronjob-interval-hours .checkbox-inline,
        .rex-cronjob-interval-days .checkbox-inline,
        .rex-cronjob-interval-days .checkbox-inline {
            display: inline;
        }
        .rex-cronjob-interval-minutes .checkbox-inline,
        .rex-cronjob-interval-hours .checkbox-inline,
        .rex-cronjob-interval-days .checkbox-inline,
        .rex-cronjob-interval-weekdays .checkbox-inline,
        .rex-cronjob-interval-months .checkbox-inline {
            margin-left: 0;
            margin-right: 10px;
        }

        .rex-cronjob-interval-minutes .checkbox-inline label,
        .rex-cronjob-interval-hours .checkbox-inline label,
        .rex-cronjob-interval-days .checkbox-inline label,
        .rex-cronjob-interval-weekdays .checkbox-inline label,
        .rex-cronjob-interval-months .checkbox-inline label {
            margin-right: 10px;
            font-weight: 400;
        }
        .rex-cronjob-interval-minutes .checkbox-inline input[type="checkbox"],
        .rex-cronjob-interval-hours .checkbox-inline input[type="checkbox"],
        .rex-cronjob-interval-days .checkbox-inline input[type="checkbox"],
        .rex-cronjob-interval-weekdays .checkbox-inline input[type="checkbox"],
        .rex-cronjob-interval-months .checkbox-inline input[type="checkbox"] {
            top: 0;
        }
        .rex-cronjob-interval-hours .checkbox-inline:nth-child(12):after,
        .rex-cronjob-interval-days .checkbox-inline:nth-child(10):after,
        .rex-cronjob-interval-days .checkbox-inline:nth-child(20):after {
            content: '\A';
            white-space: pre;
        }
        .rex-cronjob-interval-hours .checkbox-inline:nth-child(13),
        .rex-cronjob-interval-days .checkbox-inline:nth-child(11),
        .rex-cronjob-interval-days .checkbox-inline:nth-child(21) {
            margin-left: 0;
        }
    </style>

<?php
}
