<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\ApiFunction\UserImpersonate;
use Redaxo\Core\Security\BackendPasswordPolicy;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Security\User;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Validator\Validator;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$currentUser = Core::requireUser();

$message = '';
$content = '';

$userId = Request::request('user_id', 'int');
$info = [];
$warnings = [];

$user = null;

if (0 !== $userId) {
    $user = User::get($userId);
    if (!$user) {
        $userId = 0;
    }
}

// Allgemeine Infos
$userpsw = Request::post('userpsw', 'string');
$passwordChangeRequired = Request::post('password_change_required', 'bool');
$userlogin = Request::post('userlogin', 'string');
$username = Request::request('username', 'string');
$userdesc = Request::request('userdesc', 'string');
$useremail = Request::request('useremail', 'string');
$useradmin = Request::request('useradmin', 'int');
$userstatus = Request::request('userstatus', 'int');

// role
$selRole = new Select();
$selRole->setSize(1);
$selRole->setName('userrole[]');
$selRole->setId('rex-js-user-role');
$selRole->setMultiple();
$selRole->setAttribute('class', 'form-control selectpicker');
// $sel_role->addOption(I18n::msg('user_no_role'), 0);
$roles = [];
$sqlRole = Sql::factory();
$sqlRole->setQuery('SELECT id, name FROM ' . Core::getTablePrefix() . 'user_role ORDER BY name');
foreach ($sqlRole as $role) {
    $roles[$role->getValue('id')] = $role->getValue('name');
    $selRole->addOption($role->getValue('name'), $role->getValue('id'));
}
$userrole = Request::request('userrole', 'array');

// backend sprache
$selBeSprache = new Select();
$selBeSprache->setSize(1);
$selBeSprache->setName('userperm_be_sprache');
$selBeSprache->setId('rex-user-perm-mylang');
$selBeSprache->setAttribute('class', 'form-control selectpicker');
$selBeSprache->addOption('default', '');
$saveLocale = I18n::getLocale();
foreach (I18n::getLocales() as $locale) {
    I18n::setLocale($locale, false); // Locale nicht neu setzen
    $selBeSprache->addOption(I18n::msg('lang'), $locale);
}
I18n::setLocale($saveLocale, false);
$userpermBeSprache = Request::request('userperm_be_sprache', 'string');

// ----- welche startseite
$selStartpage = new Select();
$selStartpage->setSize(1);
$selStartpage->setName('userperm_startpage');
$selStartpage->setId('rex-user-perm-startpage');
$selStartpage->setAttribute('class', 'form-control selectpicker');
$selStartpage->setAttribute('data-live-search', 'true');
$selStartpage->addOption('default', '');

$startpages = [];
foreach (Controller::getPages() as $page => $pageObj) {
    if ($pageObj->hasNavigation() && !$pageObj->isHidden()) {
        $startpages[$page] = $pageObj->getTitle();
    }
}
asort($startpages);
$selStartpage->addOptions($startpages);
$userpermStartpage = Request::request('userperm_startpage', 'string');

// --------------------------------- Title

// --------------------------------- FUNCTIONS
$fUNCUPDATE = '';
$fUNCAPPLY = '';
$fUNCDELETE = '';
if ($user && ($currentUser->admin || !$user->admin)) {
    $fUNCUPDATE = Request::request('FUNC_UPDATE', 'string');
    $fUNCAPPLY = Request::request('FUNC_APPLY', 'string');
    $fUNCDELETE = Request::request('FUNC_DELETE', 'string');
} else {
    $userId = 0;
}
$fUNCADD = Request::request('FUNC_ADD', 'string');
$save = Request::request('save', 'int');
$adminchecked = '';

$passwordPolicy = BackendPasswordPolicy::factory();

if ($fUNCUPDATE || $fUNCAPPLY || ($fUNCADD && $save)) {
    if (!CsrfToken::factory('user_edit')->isValid()) {
        $warnings[] = I18n::msg('csrf_token_invalid');
    }

    if ($useremail && !Validator::factory()->email($useremail)) {
        $warnings[] = I18n::msg('invalid_email');
    }

    if ($userpsw && (true !== $msg = $passwordPolicy->check($userpsw, $userId ?: null))) {
        if ($currentUser->admin) {
            $msg .= ' ' . I18n::msg('password_admin_notice');
        }
        $warnings[] = $msg;
    }
}

if ($warnings) {
    // do not save
} elseif ('' != $fUNCUPDATE || '' != $fUNCAPPLY) {
    $loginReset = Request::request('logintriesreset', 'int');
    $userstatus = Request::request('userstatus', 'int');

    if ($currentUser->admin && $userId === $currentUser->id) {
        $useradmin = 1;
    }

    $updateuser = Sql::factory();
    $updateuser->setTable(Core::getTablePrefix() . 'user');
    $updateuser->setWhere(['id' => $userId]);
    $updateuser->setValue('name', $username);
    $updateuser->setValue('role', implode(',', $userrole));
    $updateuser->setValue('admin', $currentUser->admin && 1 == $useradmin ? 1 : 0);
    $updateuser->setValue('language', $userpermBeSprache);
    $updateuser->setValue('startpage', $userpermStartpage);
    $updateuser->addGlobalUpdateFields();
    $updateuser->setValue('description', $userdesc);
    $updateuser->setValue('email', $useremail);
    if (1 == $loginReset) {
        $updateuser->setValue('login_tries', '0');
    }
    if (1 == $userstatus) {
        $updateuser->setValue('status', 1);
    } else {
        $updateuser->setValue('status', 0);
    }

    $passwordHash = null;
    if ('' != $userpsw) {
        $passwordHash = Login::passwordHash($userpsw);
        $updateuser->setValue('password', $passwordHash);
        $updateuser->setDateTimeValue('password_changed', time());
        $updateuser->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash));
    }

    $updateuser->setValue('password_change_required', (int) $passwordChangeRequired);

    $updateuser->update();

    $info[] = I18n::msg('user_data_updated');

    User::clearInstance($userId);
    $user = User::require($userId);

    if (null !== $passwordHash && $userId == $currentUser->id) {
        Core::getProperty('login')->changedPassword($passwordHash);
    }

    Extension::dispatch(new ExtensionPoint('USER_UPDATED', '', [
        'id' => $userId,
        'user' => $user,
        'password' => $userpsw,
    ], true));

    if (null !== $passwordHash) {
        UserSession::getInstance()->removeSessionsExceptCurrent($userId);
    }

    if ('' != $fUNCUPDATE) {
        $userId = 0;
        $fUNCUPDATE = '';
    }
} elseif ('' != $fUNCDELETE) {
    // man kann sich selbst nicht loeschen..
    if ($currentUser->id == $userId) {
        $warnings[] = I18n::msg('user_notdeleteself');
    } elseif (!CsrfToken::factory('user_delete')->isValid()) {
        $warnings[] = I18n::msg('csrf_token_invalid');
    } else {
        $deleteuser = Sql::factory();
        $deleteuser->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'user WHERE id = ? LIMIT 1', [$userId]);
        $info[] = I18n::msg('user_deleted');

        User::clearInstance($userId);

        Extension::dispatch(new ExtensionPoint('USER_DELETED', '', [
            'id' => $userId,
            'user' => $user,
        ], true));
    }

    $userId = 0;
} elseif ('' != $fUNCADD && 1 == $save) {
    $adduser = Sql::factory();
    $adduser->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'user WHERE login = ?', [$userlogin]);

    if (0 == $adduser->getRows() && '' != $userlogin && '' != $userpsw) {
        $userpswHash = Login::passwordHash($userpsw);

        $adduser = Sql::factory();
        $adduser->setTable(Core::getTablePrefix() . 'user');
        $adduser->setValue('name', $username);
        $adduser->setValue('password', $userpswHash);
        $adduser->setValue('login', $userlogin);
        $adduser->setValue('description', $userdesc);
        $adduser->setValue('email', $useremail);
        $adduser->setValue('admin', $currentUser->admin && 1 == $useradmin ? 1 : 0);
        $adduser->setValue('language', $userpermBeSprache);
        $adduser->setValue('startpage', $userpermStartpage);
        $adduser->setValue('role', implode(',', $userrole));
        $adduser->addGlobalCreateFields();
        $adduser->addGlobalUpdateFields();
        $adduser->setDateTimeValue('password_changed', time());
        $adduser->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords(null, $userpswHash));
        $adduser->setValue('password_change_required', (int) $passwordChangeRequired);
        if (1 == $userstatus) {
            $adduser->setValue('status', 1);
        } else {
            $adduser->setValue('status', 0);
        }

        $adduser->insert();
        $userId = 0;
        $fUNCADD = '';
        $info[] = I18n::msg('user_added');

        Extension::dispatch(new ExtensionPoint('USER_ADDED', '', [
            'id' => $adduser->getLastId(),
            'user' => User::require($adduser->getLastId()),
            'password' => $userpsw,
        ], true));
    } else {
        if (1 == $useradmin) {
            $adminchecked = 'checked="checked"';
        }

        // userrole
        $selRole->setSelected($userrole);

        // userperm_be_sprache
        if ('' == $userpermBeSprache) {
            $userpermBeSprache = 'default';
        }
        $selBeSprache->setSelected($userpermBeSprache);

        // userperm_startpage
        if ('' == $userpermStartpage) {
            $userpermStartpage = 'default';
        }
        $selStartpage->setSelected($userpermStartpage);

        if ($adduser->getRows()) {
            $warnings[] = I18n::msg('user_login_exists');
        }
        if (!$userlogin) {
            $warnings[] = I18n::msg('user_missing_login');
        }
        if (!$userpsw) {
            $warnings[] = I18n::msg('user_missing_password');
        }
    }
} else {
    // default value for new users (for existing users it is replaced after reading the user from db)
    $passwordChangeRequired = true;
}

// ---------------------------------- ERR MSG

if (!empty($info)) {
    $message .= Message::info(implode('<br/>', $info));
}

if (!empty($warnings)) {
    $message .= Message::warning(implode('<br/>', $warnings));
}

echo ApiFunction::getMessage();

// --------------------------------- FORMS

$SHOW = true;

if ('' != $fUNCADD || $user) {
    $SHOW = false;

    // whether the user is editing his own account
    $self = $userId == $currentUser->id;

    $statuschecked = '';
    if ('' != $fUNCADD) {
        $statuschecked = 'checked="checked"';
    }

    if ($user) {
        // User Edit

        $formLabel = I18n::msg('edit_user');
        $addHidden = '<input type="hidden" name="user_id" value="' . $userId . '" />';
        $addUserLogin = '<p class="form-control-static">' . escape($user->login) . '</p>';

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage() . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="FUNC_UPDATE" value="1" ' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('user_save') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-apply" type="submit" name="FUNC_APPLY" value="1" ' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('user_apply') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
        unset($formElements);

        if (!$fUNCUPDATE && !$fUNCAPPLY) {
            $sql = Sql::factory();
            $sql->setQuery('select * from ' . Core::getTablePrefix() . 'user where id=' . $userId);

            if (1 == $sql->getRows()) {
                $passwordChangeRequired = (bool) $sql->getValue('password_change_required');
                $useradmin = $sql->getValue('admin');
                $userstatus = $sql->getValue(Core::getTablePrefix() . 'user.status');
                $userrole = $sql->getValue(Core::getTablePrefix() . 'user.role');
                if ('' == $userrole) {
                    $userrole = [];
                } else {
                    $userrole = explode(',', $userrole);
                }
                $userpermBeSprache = $sql->getValue('language');
                $userpermStartpage = $sql->getValue('startpage');
                $username = $sql->getValue(Core::getTablePrefix() . 'user.name');
                $userdesc = $sql->getValue(Core::getTablePrefix() . 'user.description');
                $useremail = $sql->getValue(Core::getTablePrefix() . 'user.email');
            }
        }

        if ($useradmin) {
            $adminchecked = 'checked="checked"';
        } else {
            $adminchecked = '';
        }

        if (1 == $userstatus) {
            $statuschecked = 'checked="checked"';
        } else {
            $statuschecked = '';
        }

        $selRole->setSelected($userrole);
        $selBeSprache->setSelected($userpermBeSprache);
        $selStartpage->setSelected($userpermStartpage);

        if ($currentUser->admin) {
            $disabled = $self ? ' disabled="disabled"' : '';
            $addAdminChkbox = '<input type="checkbox" id="rex-js-user-admin" name="useradmin" value="1" ' . $adminchecked . $disabled . ' />';
        } else {
            $addAdminChkbox = '';
        }

        // Der Benutzer kann sich selbst den Status nicht entziehen
        if ($self && '' != $statuschecked) {
            $addStatusChkbox = '<input type="hidden" name="userstatus" value="1" /><input type="checkbox" id="rex-user-status" name="userstatus" value="1" ' . $statuschecked . ' disabled="disabled" />';
        } else {
            $addStatusChkbox = '<input type="checkbox" id="rex-user-status" name="userstatus" value="1" ' . $statuschecked . ' />';
        }
    } else {
        // User Add
        $formLabel = I18n::msg('create_user');
        $addHidden = '<input type="hidden" name="FUNC_ADD" value="1" />';
        if ($currentUser->admin) {
            $addAdminChkbox = '<input type="checkbox" id="rex-js-user-admin" name="useradmin" value="1" ' . $adminchecked . ' />';
        } else {
            $addAdminChkbox = '';
        }
        $addStatusChkbox = '<input type="checkbox" id="rex-user-status" name="userstatus" value="1" ' . $statuschecked . ' />';
        $addUserLogin = '<input class="form-control" type="text" id="rex-user-login" name="userlogin" value="' . escape($userlogin) . '" required maxlength="255" autofocus autocomplete="username" inputmode="email" autocorrect="off" autocapitalize="off" />';

        $formElements = [];

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="function" value="1" ' . Core::getAccesskey(I18n::msg('add_user'), 'save') . '>' . I18n::msg('add_user') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
        unset($formElements);
    }

    $content .= '
            <fieldset>
                <input type="hidden" name="subpage" value="" />
                <input type="hidden" name="save" value="1" />
                ' . $addHidden;

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-user-login" class="required">' . I18n::msg('login_name') . '</label>';
    $n['field'] = $addUserLogin;
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-js-user-password" class="required">' . I18n::msg('password') . '</label>';
    $n['field'] = '<input class="form-control" type="password" id="rex-js-user-password" name="userpsw" autocomplete="new-password" autocorrect="off" autocapitalize="off" ' . Str::buildAttributes($passwordPolicy->getHtmlAttributes()) . ' />';
    $n['note'] = $passwordPolicy->getDescription();

    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('flush', true);
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-user-password-change-required">' . I18n::msg('user_password_change_required') . '</label>';
    $checked = $passwordChangeRequired && !$self ? ' checked="checked"' : '';
    $disabled = $self ? ' disabled="disabled"' : '';
    $n['field'] = '<input type="checkbox" id="rex-user-password-change-required" name="password_change_required" value="1" ' . $checked . $disabled . ' />';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/checkbox.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-user-name">' . I18n::msg('name') . '</label>';
    $n['field'] = '<input class="form-control" type="text" id="rex-user-name" name="username" value="' . escape($username) . '"  autocomplete="name" maxlength="255" />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-description">' . I18n::msg('description') . '</label>';
    $n['field'] = '<input class="form-control" type="text" id="rex-user-description" name="userdesc" value="' . escape($userdesc) . '" autocomplete="off" />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-email">' . I18n::msg('email') . '</label>';
    $n['field'] = '<input class="form-control" type="email" placeholder="user@example.org" id="rex-user-email" name="useremail" value="' . escape($useremail) . '"  autocomplete="email" maxlength="255" />';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('flush', true);
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $formElements = [];

    if ($addAdminChkbox) {
        $n = [];
        $n['label'] = '<label for="rex-js-user-admin">' . I18n::msg('user_admin') . '</label>';
        $n['field'] = $addAdminChkbox;
        $n['note'] = I18n::msg('user_admin_note');
        $formElements[] = $n;
    }

    $n = [];
    $n['label'] = '<label for="rex-user-status">' . I18n::msg('user_status_active') . '</label>';
    $n['field'] = $addStatusChkbox;
    $formElements[] = $n;

    if ($userId > 0 && ($loginTries = $user->getValue('login_tries')) > 0) {
        $n = [];
        $n['label'] = '<label for="rex-user-logintriesreset">' . I18n::msg('user_reset_tries', $loginTries) . '</label>';
        $n['field'] = '<input type="checkbox" id="rex-user-logintriesreset" name="logintriesreset" value="1" />';
        $formElements[] = $n;
    }

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/checkbox.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-js-user-role">' . I18n::msg('user_role') . '</label>';
    $n['field'] = $selRole->get();
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-perm-startpage">' . I18n::msg('startpage') . '</label>';
    $n['field'] = $selStartpage->get();
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-perm-mylang">' . I18n::msg('backend_language') . '</label>';
    $n['field'] = $selBeSprache->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('group', true);
    $fragment->setVar('flush', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $content .= '</fieldset>';

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formLabel);
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    $content = $fragment->parse('core/page/section.php');

    $content = '
        <form id="rex-form-user" action="' . Url::currentBackendPage() . '" method="post">
            ' . CsrfToken::factory('user_edit')->getHiddenField() . '
            ' . $content . '
        </form>

        <script type="text/javascript" nonce="' . Response::getNonce() . '">
        <!--
        jQuery(function($) {
            $("#rex-js-user-admin").change(function() {
                 if ($(this).is(":checked"))
                     $("#rex-js-user-role").prop("disabled", true);
                 else
                     $("#rex-js-user-role").prop("disabled", false);
                 $("#rex-js-user-role").selectpicker("refresh");
            }).change();
        });
        //-->
        </script>';

    echo $message;
    echo $content;

    if ($userId > 0) {
        require Path::core('pages/profile/auth_methods.php');
        require Path::core('pages/profile/sessions.php');
    }
}

// ---------------------------------- Userliste

if ($SHOW) {
    $query = trim(Request::request('user_search', 'string', ''));
    $fragment = new Fragment();
    $fragment->setVar('id', 'rex-users-addon-search');
    $fragment->setVar('name', 'user_search');
    $fragment->setVar('autofocus', !Request::request('function', 'bool'));
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
            IF(`admin`, "Admin", IFNULL((SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR "' . $separator . '") FROM ' . Core::getTable('user_role') . ' r WHERE FIND_IN_SET(r.id, u.role)), "' . $noRole . '")) as role,
            status,
            lastlogin
        FROM ' . Core::getTable('user') . ' u
        ' . $where . '
    ', defaultSort: ['name' => 'asc']);
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-user" title="' . I18n::msg('user_status_active') . '"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['FUNC_ADD' => '1']) . '"' . Core::getAccesskey(I18n::msg('create_user'), 'add') . ' title="' . I18n::msg('create_user') . '"><i class="rex-icon rex-icon-add-user"></i></a>';
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
    }, ['roles' => $roles]);
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
    $list->setColumnParams('funcs', ['FUNC_DELETE' => '1', 'user_id' => '###id###'] + CsrfToken::factory('user_delete')->getUrlParams());
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
            return sprintf('<a class="rex-link-expanded" href="%s" data-pjax="false"><i class="rex-icon rex-icon-sign-in"></i> %s</a>', $url, I18n::msg('login_impersonate'));
        });
    }

    $content .= $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('user_caption'));
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $message;
    echo $content;
}
