<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Form\User as UserData;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\SubmitButton;
use Redaxo\Core\Form\Widget\CheckboxInput;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\ApiFunction\UserImpersonate;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;
use Redaxo\Core\Security\UserRole;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$currentUser = Core::requireUser();

$func = Request::request('func', 'string');
$userId = Request::request('user_id', 'int');

// non-admins must not edit or delete admins
$user = $userId ? User::get($userId) : null;
if ($user && $user->admin && !$currentUser->admin) {
    $user = null;
}

// the form submits to the current url, so the message of the previous save must not appear again
$message = 'get' !== Request::requestMethod() ? '' : match (Request::get('saved', 'string')) {
    'added' => Message::info(I18n::msg('user_added')),
    'updated' => Message::info(I18n::msg('user_data_updated')),
    default => '',
};

if ('delete' === $func) {
    if (!$user) {
        // nothing to delete
    } elseif ($currentUser->id === $user->id) {
        $message = Message::warning(I18n::msg('user_notdeleteself'));
    } elseif (!CsrfToken::factory('user_delete')->isValid()) {
        $message = Message::warning(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM rex_user WHERE id = ? LIMIT 1', [$user->id]);
        User::clearInstance($user->id);
        $message = Message::info(I18n::msg('user_deleted'));

        Extension::dispatch(new ExtensionPoint('USER_DELETED', '', [
            'id' => $user->id,
            'user' => $user,
        ], true));
    }

    $func = '';
    $user = null;
}

echo ApiFunction::getMessage();

if ('add' === $func || $user) {
    $data = new UserData();
    // whether the user is editing their own account
    $self = $user && $currentUser->id === $user->id;
    $loginTries = 0;

    if ($user) {
        $sql = Sql::factory()->setQuery('SELECT * FROM rex_user WHERE id = ?', [$user->id]);
        $data->login = $user->login;
        $data->passwordChangeRequired = $sql->getBoolValue('password_change_required');
        $data->name = $user->name;
        $data->description = $sql->getNullableStringValue('description');
        $data->email = $user->email;
        $data->admin = $user->admin;
        $data->status = $sql->getBoolValue('status');
        $data->roles = UserRole::getIdsForUser($user->id);
        $data->startPage = $user->startPage;
        $data->language = $user->language;
        $loginTries = $sql->getIntValue('login_tries');
    }

    $fields = ['password', 'passwordChangeRequired', 'name', 'description', 'email', 'status', 'roles', 'startPage', 'language'];
    if (!$user) {
        $fields[] = 'login';
    }
    if ($currentUser->admin) {
        $fields[] = 'admin';
    }
    if ($loginTries > 0) {
        $fields[] = 'resetLoginTries';
    }

    // users can neither lock themselves out nor change their own admin status
    $disabled = [];
    if ($self) {
        $disabled = ['passwordChangeRequired', 'status', ...$currentUser->admin ? ['admin', 'roles'] : []];
    }

    $form = new Form(
        $data,
        title: new Translatable($user ? 'edit_user' : 'create_user'),
        only: $fields,
        disabled: $disabled,
        buttons: $user ? [SubmitButton::save(new Translatable('user_save')), new SubmitButton('apply', new Translatable('user_apply'), accesskey: 'apply')] : [SubmitButton::save(new Translatable('add_user'))],
        cancelUrl: Url::currentBackendPage(),
    );

    $form->handleRequest();
    $passwordPolicy = BackendLogin::getPasswordPolicy();

    // checked on the submitted raw values, so the errors appear together with those of the other fields
    if ($form->isSubmitted()) {
        $login = $user ? '' : $form->field('login')->raw;
        if (is_string($login) && '' !== $login && User::forLogin($login)) {
            $form->addError(new Translatable('user_login_exists'), 'login');
        }

        $password = $form->field('password')->raw;
        if ('' === $password) {
            if (!$user) {
                $form->addError(new Translatable('form_required'), 'password');
            }
        } elseif (is_string($password) && true !== $msg = $passwordPolicy->check($password, $user?->id)) {
            $form->addError($currentUser->admin ? $msg . ' ' . I18n::msg('password_admin_notice') : $msg, 'password');
        }
    }

    if ($form->isValid()) {
        $sql = Sql::factory();
        $sql->setTable('rex_user');
        $sql->setValue('name', $data->name);
        $sql->setValue('description', $data->description);
        $sql->setValue('email', $data->email);
        $sql->setValue('startpage', $data->startPage);
        $sql->setValue('language', $data->language);
        $sql->setValue('password_change_required', (int) $data->passwordChangeRequired);
        $sql->setValue('status', (int) $data->status);
        if ($form->has('admin')) {
            $sql->setValue('admin', (int) $data->admin);
        }
        if ($data->resetLoginTries) {
            $sql->setValue('login_tries', 0);
        }

        $passwordHash = null;
        if (null !== $data->password) {
            $passwordHash = BackendLogin::passwordHash($data->password);
            $sql->setValue('password', $passwordHash);
            $sql->setDateTimeValue('password_changed', time());
            $sql->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash));
        }

        $sql->addGlobalUpdateFields();

        if ($user) {
            $sql->setWhere(['id' => $user->id]);
            $sql->update();
            $id = $user->id;
        } else {
            $sql->setValue('login', $data->login);
            $sql->addGlobalCreateFields();
            $sql->insert();
            $id = $sql->getLastId();
        }

        UserRole::setForUser($id, $data->roles);
        User::clearInstance($id);

        if ($user) {
            if (null !== $passwordHash) {
                if ($self) {
                    BackendLogin::requireCurrent()->changedPassword($passwordHash);
                }
                UserSession::getInstance()->removeSessionsExceptCurrent($id);
            }

            Extension::dispatch(new ExtensionPoint('USER_UPDATED', '', [
                'id' => $id,
                'user' => User::require($id),
                'password' => $data->password ?? '',
            ], true));
        } else {
            Extension::dispatch(new ExtensionPoint('USER_ADDED', '', [
                'id' => $id,
                'user' => User::require($id),
                'password' => $data->password ?? '',
            ], true));
        }

        $saved = $user ? 'updated' : 'added';
        Response::sendRedirect(Url::currentBackendPage($form->isSubmittedBy('apply') ? ['user_id' => $id, 'saved' => $saved] : ['saved' => $saved]));
    }

    echo $message;

    echo $form->capture(static function (Form $form) use ($user, $loginTries): void { ?>
        <section class="rex-page-section">
            <div class="panel panel-edit">
                <header class="panel-heading"><div class="panel-title"><?= Html::from($form->title ?? '') ?></div></header>
                <div class="panel-body">
                    <?php foreach ($form->getFormErrors() as $error): ?>
                        <?= Message::error((string) Html::from($error)) ?>
                    <?php endforeach ?>
                    <?php if ($user): ?>
                        <?= new FieldRow(Html::raw('<p class="form-control-static">' . escape($user->login) . '</p>'), new Translatable('login_name'))->render() ?>
                    <?php endif ?>
                    <?php foreach (['login', 'password', 'passwordChangeRequired', 'name', 'description', 'email', 'admin', 'status'] as $name): ?>
                        <?php if ($form->has($name)): ?>
                            <?= $form->row($name) ?>
                        <?php endif ?>
                    <?php endforeach ?>
                    <?php if ($form->has('resetLoginTries')): ?>
                        <?php $field = $form->field('resetLoginTries') ?>
                        <?= new FieldRow(new CheckboxInput($field->inputName, new Translatable('user_reset_tries', $loginTries), '1' === $field->raw, id: $field->id))->render() ?>
                    <?php endif ?>
                    <?php foreach (['roles', 'startPage', 'language'] as $name): ?>
                        <?= $form->row($name) ?>
                    <?php endforeach ?>
                </div>
                <footer class="panel-footer">
                    <div class="rex-form-panel-footer">
                        <?= $form->buttons() ?>
                    </div>
                </footer>
            </div>
        </section>
    <?php });
    ?>
    <script nonce="<?= Response::getNonce() ?>">
        (() => {
            const admin = document.getElementById('user-admin');
            if (!admin) {
                return;
            }
            const update = () => document.getElementById('user-roles').disabled = admin.checked;
            admin.addEventListener('change', update);
            update();
        })();
    </script>
    <?php

    if ($user) {
        $userId = $user->id;
        require Path::core('pages/profile/auth_methods.php');
        require Path::core('pages/profile/sessions.php');
    }

    return;
}

// ---------------------------------- Userliste

$query = trim(Request::request('user_search', 'string', ''));
$fragment = new Fragment();
$fragment->setVar('id', 'rex-users-addon-search');
$fragment->setVar('name', 'user_search');
$fragment->setVar('autofocus', true);
$fragment->setVar('value', $query);
$searchForm = $fragment->parse('core/form/search.php');
$toolbar = '<form method="POST" action="">' . $searchForm . '</form>';

$where = '';
if ($query) {
    $sql = Sql::factory();
    $query = $sql->escape('%' . $sql->escapeLikeWildcards($query) . '%');
    $where = 'WHERE `name` LIKE ' . $query . ' OR `login` LIKE ' . $query;
}

// use string starting with "_" to have users without role at bottom when sorting by role ASC
$noRole = '_no_role';
$separator = "\0,\0";
$list = DataList::factory('
    SELECT
        id,
        IF(name <> "", name, login) as name,
        login,
        `admin`,
        IF(`admin`, "Admin", IFNULL((
            SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR "' . $separator . '")
            FROM rex_user_role_assignment a
            JOIN rex_user_role r ON r.id = a.role_id
            WHERE a.user_id = u.id
        ), "' . $noRole . '")) as role,
        status,
        lastlogin
    FROM rex_user u
    ' . $where . '
', defaultSort: ['name' => 'asc']);
$list->addTableAttribute('class', 'table-striped table-hover');

$tdIcon = '<i class="rex-icon rex-icon-user" title="' . I18n::msg('user_status_active') . '"></i>';
$thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"' . Accesskey::attributes(I18n::msg('create_user'), 'add') . '><i class="rex-icon rex-icon-add-user"></i></a>';
$list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
$list->setColumnParams($thIcon, ['user_id' => '###id###']);
$list->setColumnFormat($thIcon, 'custom', static function () use ($currentUser, $list, $thIcon, $tdIcon) {
    if (!$list->getValue('status')) {
        $tdIcon = str_replace('rex-icon-user', 'rex-icon-user-inactive text-muted', $tdIcon);
        $tdIcon = str_replace(I18n::msg('user_status_active'), I18n::msg('user_status_inactive'), $tdIcon);
    }
    return !$list->getValue('admin') || $currentUser->admin ? $list->getColumnLink($thIcon, $tdIcon) : $tdIcon;
});

$list->removeColumn('admin');
$list->removeColumn('status');

$list->setColumnLabel('id', 'Id');
$list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id">###VALUE###</td>']);
$list->setColumnSortable('id');

$list->setColumnLabel('name', I18n::msg('name'));
$list->setColumnParams('name', ['user_id' => '###id###']);
$list->setColumnFormat('name', 'custom', static function () use ($currentUser, $list) {
    $name = escape($list->getValue('name'));
    return !$list->getValue('admin') || $currentUser->admin ? $list->getColumnLink('name', $name) : $name;
});
$list->setColumnSortable('name');

$list->setColumnLabel('login', I18n::msg('login'));
$list->setColumnFormat('login', 'custom', static function () use ($list) {
    $login = escape($list->getValue('login'));
    if (!$list->getValue('status')) {
        $login = '<span class="text-muted">' . $login . '</span>';
    }
    return $login;
});
$list->setColumnSortable('login');

$list->setColumnLabel('role', I18n::msg('user_role'));
$list->setColumnFormat('role', 'custom', static function () use ($list, $noRole, $separator) {
    $roles = $list->getValue('role');
    if ($noRole === $roles) {
        return '<span class="label label-warning">' . I18n::msg('user_no_role') . '</span>';
    }
    if ($list->getValue('admin')) {
        return '<span class="label label-success">' . I18n::msg('user_admin') . '</span>';
    }

    return '<div class="rex-docs"><ul class="small"><li>' . implode('</li><li>', explode($separator, escape($roles))) . '</li></ul></div>';
});
$list->setColumnSortable('role');

$list->setColumnLabel('lastlogin', I18n::msg('last_login'));
$list->setColumnFormat('lastlogin', 'intlDateTime');
$list->setColumnSortable('lastlogin', 'desc');

$colspan = $currentUser->admin ? 3 : 2;
$list->addColumn(I18n::msg('user_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
$list->setColumnLayout(I18n::msg('user_functions'), ['<th class="rex-table-action" colspan="' . $colspan . '">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
$list->setColumnParams(I18n::msg('user_functions'), ['user_id' => '###id###']);
$list->setColumnFormat(I18n::msg('user_functions'), 'custom', static function () use ($currentUser, $list) {
    $edit = '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit');
    return !$list->getValue('admin') || $currentUser->admin ? $list->getColumnLink(I18n::msg('user_functions'), $edit) : $edit;
});

$list->addColumn('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
$list->setColumnLayout('funcs', ['', '<td class="rex-table-action">###VALUE###</td>']);
$list->setColumnParams('funcs', ['func' => 'delete', 'user_id' => '###id###'] + CsrfToken::factory('user_delete')->getUrlParams());
$list->setColumnFormat('funcs', 'custom', static function () use ($currentUser, $list) {
    if (
        $list->getValue('id') == $currentUser->id
        || $list->getValue('admin') && !$currentUser->admin
        || ($impersonator = Core::getImpersonator()) && $list->getValue('id') == $impersonator->id
    ) {
        return '<span class="rex-text-disabled"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('user_delete') . '</span>';
    }
    return $list->getColumnLink('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('user_delete'));
});
$list->addLinkAttribute('funcs', 'data-confirm', I18n::msg('delete') . ' ?');

if ($currentUser->admin) {
    $list->addColumn('impersonate', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout('impersonate', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnFormat('impersonate', 'custom', static function () use ($currentUser, $list) {
        if (Core::getImpersonator() || $list->getValue('id') == $currentUser->id) {
            return '<span class="rex-text-disabled"><i class="rex-icon rex-icon-sign-in"></i> ' . I18n::msg('login_impersonate') . '</span>';
        }

        $url = Url::currentBackendPage(['_impersonate' => $list->getValue('id')] + UserImpersonate::getUrlParams());
        return sprintf('<a class="rex-link-expanded" href="%s"><i class="rex-icon rex-icon-sign-in"></i> %s</a>', $url, I18n::msg('login_impersonate'));
    });
}

$content = $list->get();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('user_caption'));
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

echo $message;
echo $content;
