<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\PermissionSelectField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$id = Request::request('id', 'int');

$message = '';
$content = '';

if ('delete' == $func) {
    if (!CsrfToken::factory('user_role_delete')->isValid()) {
        $message = Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        $sql = Sql::factory();
        $sql->setQuery('DELETE FROM ' . Core::getTable('user_role') . ' WHERE id = ? LIMIT 1', [$id]);
        $message = Message::info(I18n::msg('user_role_deleted'));
    }

    $func = '';
}

if ('' == $func) {
    $title = I18n::msg('user_role_caption');

    $list = DataList::factory('SELECT id, name FROM ' . Core::getTablePrefix() . 'user_role ORDER BY name', 100);
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-userrole"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add', 'default_value' => 1]) . '"' . Core::getAccesskey(I18n::msg('create_user_role'), 'add') . ' title="' . I18n::msg('create_user_role') . '"><i class="rex-icon rex-icon-add-userrole"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'id' => '###id###']);

    $list->setColumnLabel('id', I18n::msg('id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id">###VALUE###</td>']);

    $list->setColumnLabel('name', I18n::msg('name'));
    $list->setColumnParams('name', ['func' => 'edit', 'id' => '###id###']);

    $list->addColumn('edit', '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLabel('edit', I18n::msg('user_functions'));
    $list->setColumnLayout('edit', ['<th class="rex-table-action" colspan="3">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('edit', ['func' => 'edit', 'id' => '###id###']);

    $list->addColumn('duplicate', '<i class="rex-icon rex-icon-duplicate"></i> ' . I18n::msg('user_role_duplicate'));
    $list->setColumnLabel('duplicate', I18n::msg('user_functions'));
    $list->setColumnLayout('duplicate', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('duplicate', ['func' => 'duplicate', 'id' => '###id###']);

    $list->addColumn('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('user_role_delete'));
    $list->setColumnLabel('funcs', I18n::msg('user_functions'));
    $list->setColumnLayout('funcs', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('funcs', ['func' => 'delete', 'id' => '###id###'] + CsrfToken::factory('user_role_delete')->getUrlParams());
    $list->addLinkAttribute('funcs', 'data-confirm', I18n::msg('delete') . ' ?');

    $content .= $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $title);
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');
} else {
    $title = 'edit' == $func ? I18n::msg('edit_user_role') : I18n::msg('add_user_role');

    $form = Form::factory(Core::getTablePrefix() . 'user_role', '', 'id = ' . $id);
    $form->addParam('id', $id);
    $form->setApplyUrl(Url::currentBackendPage());
    $form->setEditMode('edit' == $func);

    $field = $form->addTextField('name');
    $field->setLabel(I18n::msg('name'));
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY)
        ->add(ValidationRule::MAX_LENGTH, null, 255)
    ;

    $field = $form->addTextAreaField('description');
    $field->setLabel(I18n::msg('description'));

    $fieldContainer = $form->addContainerField('perms');
    $fieldContainer->setMultiple(false);
    $group = 'all';
    $fieldContainer->setActive($group);

    // Check all page permissions and add them to Permission if not already registered
    $registerImplicitePagePermissions = static function ($pages) use (&$registerImplicitePagePermissions) {
        foreach ($pages as $page) {
            foreach ($page->getRequiredPermissions() as $perm) {
                // ignore admin perm and complex perms (with "/")
                if ($perm && !in_array($perm, ['isAdmin', 'admin', 'admin[]']) && !str_contains($perm, '/') && !Permission::has($perm)) {
                    Permission::register($perm);
                }
            }
            $registerImplicitePagePermissions($page->getSubpages());
        }
    };
    $registerImplicitePagePermissions(Controller::getPages());

    foreach ([Permission::GENERAL, Permission::OPTIONS, Permission::EXTRAS] as $permgroup) {
        /** @var SelectField $field */
        $field = $fieldContainer->addGroupedField($group, 'select', $permgroup);
        $field->setLabel(I18n::msg('user_' . $permgroup));
        $select = $field->getSelect();
        $select->setMultiple(true);
        $perms = Permission::getAll($permgroup);
        asort($perms);
        $select->setSize(min(20, max(3, count($perms))));
        $select->addArrayOptions($perms);
    }

    Extension::register('REX_FORM_INPUT_CLASS', static function (ExtensionPoint $ep) {
        return 'perm_select' == $ep->getParam('inputType') ? PermissionSelectField::class : null;
    });

    $fieldIds = [];
    foreach (ComplexPermission::getAll() as $key => $class) {
        $params = $class::getFieldParams();
        if (!empty($params)) {
            /** @var PermissionSelectField $field */
            $field = $fieldContainer->addGroupedField($group, 'perm_select', $key);
            $field->setLabel($params['label']);
            $field->setCheckboxLabel($params['all_label']);
            $fieldIds[] = escape($field->getAttribute('id'), 'js');
            if (Request::request('default_value', 'boolean')) {
                $field->setValue(ComplexPermission::ALL);
            }
            if (isset($params['select'])) {
                $field->setSelect($params['select']);
            }
            $select = $field->getSelect();
            $select->setMultiple(true);
            if (isset($params['options'])) {
                $select->addArrayOptions($params['options']);
            }
            if (isset($params['sql_options'])) {
                $select->addSqlOptions($params['sql_options']);
            }
            $select->get();
            $select->setSize(min(20, max(3, $select->countOptions())));
        }
    }

    $content .= $form->get();

    if ($fieldIds) {
        $content .= '
            <script type="text/javascript" nonce="' . Response::getNonce() . '">
            <!--

            jQuery(function($) {

                function check_perm_field(field, duration) {
                    var id = field.attr("id").substr(0, field.attr("id").length - 4);
                    if(field.is(":checked"))
                        $("#"+id).closest(".rex-form-group").hide(duration).find(":input").prop("disabled", true);
                    else
                        $("#"+id).closest(".rex-form-group").show(duration).find(":input").prop("disabled", false);
                }
                $("#' . implode('-all, #', $fieldIds) . '-all").change(function(){
                    check_perm_field($(this), "slow");
                });

                $("#' . implode('-all, #', $fieldIds) . '-all").each(function(){
                    check_perm_field($(this), 0);
                });

            });

            //--></script>
        ';
    }

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $title);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');
}

echo $message;
echo $content;
