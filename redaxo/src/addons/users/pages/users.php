<?php

$currentUser = rex::requireUser();

$message = '';
$content = '';

$userId = rex_request('user_id', 'int');
$info = [];
$warnings = [];

$user = null;

if (0 !== $userId) {
    $user = rex_user::get($userId);
    if (!$user) {
        $userId = 0;
    }
}

// Allgemeine Infos
$userpsw = rex_post('userpsw', 'string');
$passwordChangeRequired = rex_post('password_change_required', 'bool');
$userlogin = rex_post('userlogin', 'string');
$username = rex_request('username', 'string');
$userdesc = rex_request('userdesc', 'string');
$useremail = rex_request('useremail', 'string');
$useradmin = rex_request('useradmin', 'int');
$userstatus = rex_request('userstatus', 'int');

// role
$selRole = new rex_select();
$selRole->setSize(1);
$selRole->setName('userrole[]');
$selRole->setId('rex-js-user-role');
$selRole->setMultiple();
$selRole->setAttribute('class', 'form-control selectpicker');
// $sel_role->addOption(rex_i18n::msg('user_no_role'), 0);
$roles = [];
$sqlRole = rex_sql::factory();
$sqlRole->setQuery('SELECT id, name FROM ' . rex::getTablePrefix() . 'user_role ORDER BY name');
foreach ($sqlRole as $role) {
    $roles[$role->getValue('id')] = $role->getValue('name');
    $selRole->addOption($role->getValue('name'), $role->getValue('id'));
}
$userrole = rex_request('userrole', 'array');

// backend sprache
$selBeSprache = new rex_select();
$selBeSprache->setSize(1);
$selBeSprache->setName('userperm_be_sprache');
$selBeSprache->setId('rex-user-perm-mylang');
$selBeSprache->setAttribute('class', 'form-control selectpicker');
$selBeSprache->addOption('default', '');
$saveLocale = rex_i18n::getLocale();
foreach (rex_i18n::getLocales() as $locale) {
    rex_i18n::setLocale($locale, false); // Locale nicht neu setzen
    $selBeSprache->addOption(rex_i18n::msg('lang'), $locale);
}
rex_i18n::setLocale($saveLocale, false);
$userpermBeSprache = rex_request('userperm_be_sprache', 'string');

// ----- welche startseite
$selStartpage = new rex_select();
$selStartpage->setSize(1);
$selStartpage->setName('userperm_startpage');
$selStartpage->setId('rex-user-perm-startpage');
$selStartpage->setAttribute('class', 'form-control selectpicker');
$selStartpage->setAttribute('data-live-search', 'true');
$selStartpage->addOption('default', '');

$startpages = [];
foreach (rex_be_controller::getPages() as $page => $pageObj) {
    if ($pageObj->hasNavigation() && !$pageObj->isHidden()) {
        $startpages[$page] = $pageObj->getTitle();
    }
}
asort($startpages);
$selStartpage->addOptions($startpages);
$userpermStartpage = rex_request('userperm_startpage', 'string');

// --------------------------------- Title

// --------------------------------- FUNCTIONS
$fUNCUPDATE = '';
$fUNCAPPLY = '';
$fUNCDELETE = '';
if (0 !== $userId && ($currentUser->isAdmin() || !$user->isAdmin())) {
    $fUNCUPDATE = rex_request('FUNC_UPDATE', 'string');
    $fUNCAPPLY = rex_request('FUNC_APPLY', 'string');
    $fUNCDELETE = rex_request('FUNC_DELETE', 'string');
} else {
    $userId = 0;
}
$fUNCADD = rex_request('FUNC_ADD', 'string');
$save = rex_request('save', 'int');
$adminchecked = '';

$passwordPolicy = rex_backend_password_policy::factory();

if ($save && ($fUNCADD || $fUNCUPDATE || $fUNCAPPLY)) {
    if (!rex_csrf_token::factory('user_edit')->isValid()) {
        $warnings[] = rex_i18n::msg('csrf_token_invalid');
    }

    if ($useremail && !rex_validator::factory()->email($useremail)) {
        $warnings[] = rex_i18n::msg('invalid_email');
    }

    if ($userpsw && (true !== $msg = $passwordPolicy->check($userpsw, $userId ?: null))) {
        if ($currentUser->isAdmin()) {
            $msg .= ' ' . rex_i18n::msg('password_admin_notice');
        }
        $warnings[] = $msg;
    }
}

if ($warnings) {
    // do not save
} elseif ('' != $fUNCUPDATE || '' != $fUNCAPPLY) {
    $loginReset = rex_request('logintriesreset', 'int');
    $userstatus = rex_request('userstatus', 'int');

    if ($currentUser->isAdmin() && $userId === $currentUser->getId()) {
        $useradmin = 1;
    }

    $updateuser = rex_sql::factory();
    $updateuser->setTable(rex::getTablePrefix() . 'user');
    $updateuser->setWhere(['id' => $userId]);
    $updateuser->setValue('name', $username);
    $updateuser->setValue('role', implode(',', $userrole));
    $updateuser->setValue('admin', $currentUser->isAdmin() && 1 == $useradmin ? 1 : 0);
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
        $passwordHash = rex_login::passwordHash($userpsw);
        $updateuser->setValue('password', $passwordHash);
        $updateuser->setDateTimeValue('password_changed', time());
        $updateuser->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash));
    }

    $updateuser->setValue('password_change_required', (int) $passwordChangeRequired);

    $updateuser->update();

    $info[] = rex_i18n::msg('user_data_updated');

    rex_user::clearInstance($userId);
    $user = rex_user::require($userId);

    if (null !== $passwordHash && $userId == $currentUser->getId()) {
        rex::getProperty('login')->changedPassword($passwordHash);
    }

    rex_extension::registerPoint(new rex_extension_point('USER_UPDATED', '', [
        'id' => $userId,
        'user' => $user,
        'password' => $userpsw,
    ], true));

    if (null !== $passwordHash) {
        rex_user_session::getInstance()->removeSessionsExceptCurrent($userId);
    }

    if ('' != $fUNCUPDATE) {
        $userId = 0;
        $fUNCUPDATE = '';
    }
} elseif ('' != $fUNCDELETE) {
    // man kann sich selbst nicht loeschen..
    if ($currentUser->getId() == $userId) {
        $warnings[] = rex_i18n::msg('user_notdeleteself');
    } elseif (!rex_csrf_token::factory('user_delete')->isValid()) {
        $warnings[] = rex_i18n::msg('csrf_token_invalid');
    } else {
        $deleteuser = rex_sql::factory();
        $deleteuser->setQuery('DELETE FROM ' . rex::getTablePrefix() . 'user WHERE id = ? LIMIT 1', [$userId]);
        $info[] = rex_i18n::msg('user_deleted');

        rex_user::clearInstance($userId);

        rex_extension::registerPoint(new rex_extension_point('USER_DELETED', '', [
            'id' => $userId,
            'user' => $user,
        ], true));
    }

    $userId = 0;
} elseif ('' != $fUNCADD && 1 == $save) {
    $adduser = rex_sql::factory();
    $adduser->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'user WHERE login = ?', [$userlogin]);

    if (0 == $adduser->getRows() && '' != $userlogin && '' != $userpsw) {
        $userpswHash = rex_login::passwordHash($userpsw);

        $adduser = rex_sql::factory();
        $adduser->setTable(rex::getTablePrefix() . 'user');
        $adduser->setValue('name', $username);
        $adduser->setValue('password', $userpswHash);
        $adduser->setValue('login', $userlogin);
        $adduser->setValue('description', $userdesc);
        $adduser->setValue('email', $useremail);
        $adduser->setValue('admin', $currentUser->isAdmin() && 1 == $useradmin ? 1 : 0);
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
        $info[] = rex_i18n::msg('user_added');

        rex_extension::registerPoint(new rex_extension_point('USER_ADDED', '', [
            'id' => $adduser->getLastId(),
            'user' => rex_user::require((int) $adduser->getLastId()),
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
            $warnings[] = rex_i18n::msg('user_login_exists');
        }
        if (!$userlogin) {
            $warnings[] = rex_i18n::msg('user_missing_login');
        }
        if (!$userpsw) {
            $warnings[] = rex_i18n::msg('user_missing_password');
        }
    }
} else {
    // default value for new users (for existing users it is replaced after reading the user from db)
    $passwordChangeRequired = true;
}

// ---------------------------------- ERR MSG

if (!empty($info)) {
    $message .= rex_view::info(implode('<br/>', $info));
}

if (!empty($warnings)) {
    $message .= rex_view::warning(implode('<br/>', $warnings));
}

echo rex_api_function::getMessage();

// --------------------------------- FORMS

$SHOW = true;

if ('' != $fUNCADD || $userId > 0) {
    $SHOW = false;

    // whether the user is editing his own account
    $self = $userId == $currentUser->getId();

    $statuschecked = '';
    if ('' != $fUNCADD) {
        $statuschecked = 'checked="checked"';
    }

    if ($userId > 0) {
        // User Edit

        $formLabel = rex_i18n::msg('edit_user');
        $addHidden = '<input type="hidden" name="user_id" value="' . $userId . '" />';
        $addUserLogin = '<p class="form-control-static">' . rex_escape($user->getLogin()) . '</p>';

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage() . '">' . rex_i18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="FUNC_UPDATE" value="1" ' . rex::getAccesskey(rex_i18n::msg('save_and_close_tooltip'), 'save') . '>' . rex_i18n::msg('user_save') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-apply" type="submit" name="FUNC_APPLY" value="1" ' . rex::getAccesskey(rex_i18n::msg('save_and_goon_tooltip'), 'apply') . '>' . rex_i18n::msg('user_apply') . '</button>';
        $formElements[] = $n;

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
        unset($formElements);

        if (!$fUNCUPDATE && !$fUNCAPPLY) {
            $sql = rex_sql::factory();
            $sql->setQuery('select * from ' . rex::getTablePrefix() . 'user where id=' . $userId);

            if (1 == $sql->getRows()) {
                $passwordChangeRequired = (bool) $sql->getValue('password_change_required');
                $useradmin = $sql->getValue('admin');
                $userstatus = $sql->getValue(rex::getTablePrefix() . 'user.status');
                $userrole = $sql->getValue(rex::getTablePrefix() . 'user.role');
                if ('' == $userrole) {
                    $userrole = [];
                } else {
                    $userrole = explode(',', $userrole);
                }
                $userpermBeSprache = $sql->getValue('language');
                $userpermStartpage = $sql->getValue('startpage');
                $username = $sql->getValue(rex::getTablePrefix() . 'user.name');
                $userdesc = $sql->getValue(rex::getTablePrefix() . 'user.description');
                $useremail = $sql->getValue(rex::getTablePrefix() . 'user.email');
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

        if ($currentUser->isAdmin()) {
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
        $formLabel = rex_i18n::msg('create_user');
        $addHidden = '<input type="hidden" name="FUNC_ADD" value="1" />';
        if ($currentUser->isAdmin()) {
            $addAdminChkbox = '<input type="checkbox" id="rex-js-user-admin" name="useradmin" value="1" ' . $adminchecked . ' />';
        } else {
            $addAdminChkbox = '';
        }
        $addStatusChkbox = '<input type="checkbox" id="rex-user-status" name="userstatus" value="1" ' . $statuschecked . ' />';
        $addUserLogin = '<input class="form-control" type="text" id="rex-user-login" name="userlogin" value="' . rex_escape($userlogin) . '" required maxlength="255" autofocus autocomplete="username" inputmode="email" autocorrect="off" autocapitalize="off" />';

        $formElements = [];

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="function" value="1" ' . rex::getAccesskey(rex_i18n::msg('add_user'), 'save') . '>' . rex_i18n::msg('add_user') . '</button>';
        $formElements[] = $n;

        $fragment = new rex_fragment();
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
    $n['label'] = '<label for="rex-user-login" class="required">' . rex_i18n::msg('login_name') . '</label>';
    $n['field'] = $addUserLogin;
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-js-user-password" class="required">' . rex_i18n::msg('password') . '</label>';
    $n['field'] = '<input class="form-control" type="password" id="rex-js-user-password" name="userpsw" autocomplete="new-password" autocorrect="off" autocapitalize="off" ' . rex_string::buildAttributes($passwordPolicy->getHtmlAttributes()) . ' />';
    $n['note'] = $passwordPolicy->getDescription();

    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('flush', true);
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-user-password-change-required">' . rex_i18n::msg('user_password_change_required') . '</label>';
    $checked = $passwordChangeRequired && !$self ? ' checked="checked"' : '';
    $disabled = $self ? ' disabled="disabled"' : '';
    $n['field'] = '<input type="checkbox" id="rex-user-password-change-required" name="password_change_required" value="1" ' . $checked . $disabled . ' />';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/checkbox.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-user-name">' . rex_i18n::msg('name') . '</label>';
    $n['field'] = '<input class="form-control" type="text" id="rex-user-name" name="username" value="' . rex_escape($username) . '"  autocomplete="name" maxlength="255" />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-description">' . rex_i18n::msg('description') . '</label>';
    $n['field'] = '<input class="form-control" type="text" id="rex-user-description" name="userdesc" value="' . rex_escape($userdesc) . '" autocomplete="off" />';
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-email">' . rex_i18n::msg('email') . '</label>';
    $n['field'] = '<input class="form-control" type="email" placeholder="user@example.org" id="rex-user-email" name="useremail" value="' . rex_escape($useremail) . '"  autocomplete="email" maxlength="255" />';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('flush', true);
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $formElements = [];

    if ($addAdminChkbox) {
        $n = [];
        $n['label'] = '<label for="rex-js-user-admin">' . rex_i18n::msg('user_admin') . '</label>';
        $n['field'] = $addAdminChkbox;
        $n['note'] = rex_i18n::msg('user_admin_note');
        $formElements[] = $n;
    }

    $n = [];
    $n['label'] = '<label for="rex-user-status">' . rex_i18n::msg('user_status_active') . '</label>';
    $n['field'] = $addStatusChkbox;
    $formElements[] = $n;

    if ($userId > 0 && $user->getValue('login_tries') > 0) {
        $n = [];
        $n['label'] = '<label for="rex-user-logintriesreset">' . rex_i18n::msg('user_reset_tries', $user->getValue('login_tries')) . '</label>';
        $n['field'] = '<input type="checkbox" id="rex-user-logintriesreset" name="logintriesreset" value="1" />';
        $formElements[] = $n;
    }

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/checkbox.php');

    $formElements = [];

    $n = [];
    $n['label'] = '<label for="rex-js-user-role">' . rex_i18n::msg('user_role') . '</label>';
    $n['field'] = $selRole->get();
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-perm-startpage">' . rex_i18n::msg('startpage') . '</label>';
    $n['field'] = $selStartpage->get();
    $formElements[] = $n;

    $n = [];
    $n['label'] = '<label for="rex-user-perm-mylang">' . rex_i18n::msg('backend_language') . '</label>';
    $n['field'] = $selBeSprache->get();
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('group', true);
    $fragment->setVar('flush', true);
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');

    $content .= '</fieldset>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $formLabel);
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    $content = $fragment->parse('core/page/section.php');

    $content = '
        <form id="rex-form-user" action="' . rex_url::currentBackendPage() . '" method="post">
            ' . rex_csrf_token::factory('user_edit')->getHiddenField() . '
            ' . $content . '
        </form>

        <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
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
        require rex_path::core('pages/profile.auth_methods.php');
        require rex_path::core('pages/profile.sessions.php');
    }
}

// ---------------------------------- Userliste

if ($SHOW) {
    $query = trim(rex_request('user_search', 'string', ''));
    $fragment = new rex_fragment();
    $fragment->setVar('id', 'rex-users-addon-search');
    $fragment->setVar('autofocus', !rex_request('function', 'bool'));
    $fragment->setVar('value', $query);
    $searchForm = $fragment->parse('core/form/search.php');
    $searchForm = str_replace('<input', '<input name="user_search"', $searchForm);
    $toolbar = '<form method="POST" action="">'.$searchForm.'</form>';

    // use string starting with "_" to have users without role at bottom when sorting by role ASC
    $noRole = '_no_role';
    $separator = "\0,\0";
    if ($query == ''){
        $list = rex_list::factory('
            SELECT
                id,
                IF(name <> "", name, login) as name,
                login,
                `admin`,
                IF(`admin`, "Admin", IFNULL((SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR "' . $separator . '") FROM ' . rex::getTable('user_role') . ' r WHERE FIND_IN_SET(r.id, u.role)), "' . $noRole . '")) as role,
                status,
                lastlogin
            FROM ' . rex::getTable('user') . ' u
        ', defaultSort: ['name' => 'asc']);
    } else {
        $db = rex_sql::factory();
        $list = rex_list::factory('
            SELECT
                id,
                IF(name <> "", name, login) as name,
                login,
                `admin`,
                IF(`admin`, "Admin", IFNULL((SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR "' . $separator . '") FROM ' . rex::getTable('user_role') . ' r WHERE FIND_IN_SET(r.id, u.role)), "' . $noRole . '")) as role,
                status,
                lastlogin
            FROM ' . rex::getTable('user') . ' u
            WHERE CONCAT(`name`, `login`) LIKE '.$db->escape('%'.$query.'%').'
        ', defaultSort: ['name' => 'asc']);
    }
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-user" title="' . rex_i18n::msg('user_status_active') . '"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['FUNC_ADD' => '1']) . '"' . rex::getAccesskey(rex_i18n::msg('create_user'), 'add') . ' title="' . rex_i18n::msg('create_user') . '"><i class="rex-icon rex-icon-add-user"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['user_id' => '###id###']);
    $list->setColumnFormat($thIcon, 'custom', static function () use ($currentUser, $list, $thIcon, $tdIcon) {
        if (!$list->getValue('status')) {
            $tdIcon = str_replace('rex-icon-user', 'rex-icon-user-inactive text-muted', $tdIcon);
            $tdIcon = str_replace(rex_i18n::msg('user_status_active'), rex_i18n::msg('user_status_inactive'), $tdIcon);
        }
        return !$list->getValue('admin') || $currentUser->isAdmin() ? $list->getColumnLink($thIcon, $tdIcon) : $tdIcon;
    });

    $list->removeColumn('admin');
    $list->removeColumn('status');

    $list->setColumnLabel('id', 'Id');
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id">###VALUE###</td>']);
    $list->setColumnSortable('id');

    $list->setColumnLabel('name', rex_i18n::msg('name'));
    $list->setColumnParams('name', ['user_id' => '###id###']);
    $list->setColumnFormat('name', 'custom', static function () use ($currentUser, $list) {
        $name = rex_escape($list->getValue('name'));
        return !$list->getValue('admin') || $currentUser->isAdmin() ? $list->getColumnLink('name', $name) : $name;
    });
    $list->setColumnSortable('name');

    $list->setColumnLabel('login', rex_i18n::msg('login'));
    $list->setColumnFormat('login', 'custom', static function () use ($list) {
        $login = rex_escape($list->getValue('login'));
        if (!$list->getValue('status')) {
            $login = '<span class="text-muted">' . $login . '</span>';
        }
        return $login;
    });
    $list->setColumnSortable('login');

    $list->setColumnLabel('role', rex_i18n::msg('user_role'));
    $list->setColumnFormat('role', 'custom', static function () use ($list, $noRole, $separator) {
        $roles = $list->getValue('role');
        if ($noRole === $roles) {
            return '<span class="label label-warning">' . rex_i18n::msg('user_no_role') . '</span>';
        }
        if ($list->getValue('admin')) {
            return '<span class="label label-success">' . rex_i18n::msg('user_admin') . '</span>';
        }

        return '<div class="rex-docs"><ul class="small"><li>' . implode('</li><li>', explode($separator, rex_escape($roles))) . '</li></ul></div>';
    }, ['roles' => $roles]);
    $list->setColumnSortable('role');

    $list->setColumnLabel('lastlogin', rex_i18n::msg('last_login'));
    $list->setColumnFormat('lastlogin', 'intlDateTime');
    $list->setColumnSortable('lastlogin', 'desc');

    $colspan = $currentUser->isAdmin() ? 3 : 2;
    $list->addColumn(rex_i18n::msg('user_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit'));
    $list->setColumnLayout(rex_i18n::msg('user_functions'), ['<th class="rex-table-action" colspan="' . $colspan . '">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(rex_i18n::msg('user_functions'), ['user_id' => '###id###']);
    $list->setColumnFormat(rex_i18n::msg('user_functions'), 'custom', static function () use ($currentUser, $list) {
        $edit = '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit');
        return !$list->getValue('admin') || $currentUser->isAdmin() ? $list->getColumnLink(rex_i18n::msg('user_functions'), $edit) : $edit;
    });

    $list->addColumn('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete'));
    $list->setColumnLayout('funcs', ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('funcs', ['FUNC_DELETE' => '1', 'user_id' => '###id###'] + rex_csrf_token::factory('user_delete')->getUrlParams());
    $list->setColumnFormat('funcs', 'custom', static function () use ($currentUser, $list) {
        if (
            $list->getValue('id') == $currentUser->getId()
            || $list->getValue('admin') && !$currentUser->isAdmin()
            || ($impersonator = rex::getImpersonator()) && $list->getValue('id') == $impersonator->getId()
        ) {
            return '<span class="rex-text-disabled"><i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('user_delete') . '</span>';
        }
        return $list->getColumnLink('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('user_delete'));
    });
    $list->addLinkAttribute('funcs', 'data-confirm', rex_i18n::msg('delete') . ' ?');

    if ($currentUser->isAdmin()) {
        $list->addColumn('impersonate', '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete'));
        $list->setColumnLayout('impersonate', ['', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnFormat('impersonate', 'custom', static function () use ($currentUser, $list) {
            if (rex::getImpersonator() || $list->getValue('id') == $currentUser->getId()) {
                return '<span class="rex-text-disabled"><i class="rex-icon rex-icon-sign-in"></i> ' . rex_i18n::msg('login_impersonate') . '</span>';
            }

            $url = rex_url::currentBackendPage(['_impersonate' => $list->getValue('id')] + rex_api_user_impersonate::getUrlParams());
            return sprintf('<a class="rex-link-expanded" href="%s" data-pjax="false"><i class="rex-icon rex-icon-sign-in"></i> %s</a>', $url, rex_i18n::msg('login_impersonate'));
        });
    }

    $content .= $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('user_caption'));
    $fragment->setVar('options', $toolbar, false);
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $message;
    echo $content;
}
