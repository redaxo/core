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
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Component\Badge;
use Redaxo\Core\View\Component\BadgeVariant;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\Renderable;

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
        <section class="rex-section rex-section--edit">
            <header class="rex-section__header"><div class="rex-section__title"><?= Html::from($form->title ?? '') ?></div></header>
            <div class="rex-section__body">
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
            <footer class="rex-section__footer">
                <div class="rex-form-panel-footer">
                    <?= $form->buttons() ?>
                </div>
            </footer>
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
$list = new DataList('
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
$list->attributes = new HtmlAttributes(['class' => ['table-striped', 'table-hover']]);

// only admins edit admins
$editUrl = static fn (array $row): ?string => !$row['admin'] || $currentUser->admin ? $list->getUrl(['user_id' => (int) $row['id']]) : null;

$list->column(
    'icon',
    Html::raw('<a class="rex-link-expanded" href="' . escape($list->getUrl(['func' => 'add'])) . '"' . Accesskey::attributes(I18n::msg('create_user'), 'add') . '><i class="rex-icon rex-icon-add-user"></i></a>'),
    static fn (array $row): Html => $row['status']
        ? Html::raw('<i class="rex-icon rex-icon-user" title="' . I18n::msg('user_status_active') . '"></i>')
        : Html::raw('<i class="rex-icon rex-icon-user-inactive text-muted" title="' . I18n::msg('user_status_inactive') . '"></i>'),
    type: ColumnType::Icon,
    link: $editUrl,
);
$list->column('id', 'Id', type: ColumnType::Id, sortable: true);
$list->column('name', I18n::msg('name'), sortable: true, link: $editUrl);
$list->column('login', I18n::msg('login'), static fn (array $row): string|Html => $row['status']
    ? (string) $row['login']
    : Html::raw('<span class="text-muted">' . escape((string) $row['login']) . '</span>'), sortable: true);
$list->column('role', I18n::msg('user_role'), static function (array $row) use ($noRole, $separator): Renderable {
    if ($noRole === $row['role']) {
        return new Badge(I18n::msg('user_no_role'), BadgeVariant::Warning);
    }
    if ($row['admin']) {
        return new Badge(I18n::msg('user_admin'), BadgeVariant::Success);
    }

    return Html::raw('<div class="rex-docs"><ul class="small"><li>' . implode('</li><li>', explode($separator, escape((string) $row['role']))) . '</li></ul></div>');
}, sortable: true);
$list->column('lastlogin', I18n::msg('last_login'), static fn (array $row): string => Formatter::intlDateTime(null === $row['lastlogin'] ? null : (string) $row['lastlogin']), sortable: 'desc');

$list->column('edit', I18n::msg('user_functions'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit')), type: ColumnType::Action, link: $editUrl);
$canDelete = static fn (array $row): bool => $row['id'] != $currentUser->id
    && ($currentUser->admin || !$row['admin'])
    && (!($impersonator = Core::getImpersonator()) || $row['id'] != $impersonator->id);
$list->column(
    'delete',
    null,
    static fn (array $row): Html => Html::raw(($canDelete($row) ? '' : '<span class="rex-text-disabled">') . '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('user_delete') . ($canDelete($row) ? '' : '</span>')),
    type: ColumnType::Action,
    link: static fn (array $row): ?string => $canDelete($row) ? $list->getUrl(['func' => 'delete', 'user_id' => (int) $row['id']] + CsrfToken::factory('user_delete')->getUrlParams()) : null,
    linkAttributes: ['data-confirm' => I18n::msg('delete') . ' ?'],
);

if ($currentUser->admin) {
    $list->column('impersonate', null, static function (array $row) use ($currentUser): Html {
        $label = '<i class="rex-icon rex-icon-sign-in"></i> ' . I18n::msg('login_impersonate');
        if (Core::getImpersonator() || $row['id'] == $currentUser->id) {
            return Html::raw('<span class="rex-text-disabled">' . $label . '</span>');
        }
        return Html::raw('<a class="rex-link-expanded" href="' . escape(Url::currentBackendPage(['_impersonate' => (int) $row['id']] + UserImpersonate::getUrlParams())) . '">' . $label . '</a>');
    }, type: ColumnType::Action);
}

$content = (string) $list->render();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('user_caption'));
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/section.php');

echo $message;
echo $content;
