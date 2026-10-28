<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Backend\Form\Profile;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\Storage\DatabaseStorage;
use Redaxo\Core\Form\SubmitButton;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\User;
use Redaxo\Core\Security\WebAuthn;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$error = '';
$success = '';
$user = Core::requireUser();
$userId = $user->id;

$login = BackendLogin::requireCurrent();
$passwordChangeRequired = $login->requiresPasswordChange();

// Allgemeine Infos
$userpsw = Request::request('userpsw', 'string');
$passkey = Request::request('passkey', 'string');
$userpswNew1 = Request::request('userpsw_new_1', 'string');
$userpswNew2 = Request::request('userpsw_new_2', 'string');

$csrfToken = CsrfToken::factory('profile');
$passwordPolicy = BackendLogin::getPasswordPolicy();
$webauthn = new WebAuthn();

// --------------------------------- Title
echo View::title(I18n::msg('profile_title'), '');

// --------------------------------- PROFILE

$profile = new Profile();
$profileStorage = new DatabaseStorage('rex_user', $userId, globalFields: true);
$profileStorage->load($profile);

$profileForm = new Form(
    $profile,
    title: new Translatable('profile_myprofile'),
    disabled: null === Appearance::$forcedTheme ? [] : ['theme'],
    buttons: [SubmitButton::save(new Translatable('profile_save'))],
);

if ($profileForm->handleRequest()) {
    $profileStorage->save($profile);
    User::clearInstance($userId);

    Extension::dispatch(new ExtensionPoint('PROFILE_UPDATED', '', [
        'user_id' => $userId,
        'user' => User::require($userId),
    ], true));

    // trigger a fullpage-reload which immediately reflects a possible changed language
    Response::sendRedirect(Url::currentBackendPage(['rex_user_updated' => true]));
}

// Restore success message after redirect
// is necessary to show the whole page in the selected language
if (Request::request('rex_user_updated', 'bool', false)) {
    $success = I18n::msg('user_data_updated');
}

$verifyLogin = static function () use ($user, $login, $userpsw, $webauthn): bool|string {
    if (!$login->getPasskey()) {
        if (!$userpsw || !BackendLogin::passwordVerify($userpsw, $user->password ?? '')) {
            return I18n::msg('user_psw_verify_error');
        }

        return true;
    }

    $result = $webauthn->processGet(Request::post('passkey_verify', 'string'));
    if ($result) {
        [$id, $passkeyUser] = $result;

        if ($id === $login->getPasskey() && $passkeyUser->id === $user->id) {
            return true;
        }
    }

    return I18n::msg('passkey_verify_error');
};

if (Request::post('upd_psw_button', 'bool')) {
    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } elseif (true !== $msg = $verifyLogin()) {
        $error = $msg;
    } elseif (!$userpswNew1 || $userpswNew1 != $userpswNew2) {
        $error = I18n::msg('user_psw_new_error');
    } elseif (true !== $msg = $passwordPolicy->check($userpswNew1, $userId)) {
        $error = $msg;
    } elseif ($passwordChangeRequired && $userpsw === $userpswNew1) {
        $error = I18n::msg('password_not_changed');
    } else {
        $userpswNew1 = BackendLogin::passwordHash($userpswNew1);

        $updateuser = Sql::factory();
        $updateuser->setTable('rex_user');
        $updateuser->setWhere(['id' => $userId]);
        $updateuser->setValue('password', $userpswNew1);
        $updateuser->addGlobalUpdateFields();
        $updateuser->setValue('password_change_required', 0);
        $updateuser->setDateTimeValue('password_changed', time());
        $updateuser->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $userpswNew1));

        $updateuser->update();
        User::clearInstance($userId);

        $success = I18n::msg('user_psw_updated');

        if ($passwordChangeRequired) {
            $passwordChangeRequired = false;
        }
        $login->changedPassword($userpswNew1);

        Extension::dispatch(new ExtensionPoint('PASSWORD_UPDATED', '', [
            'user_id' => $userId,
            'user' => User::require($userId),
            'password' => $userpswNew2,
        ], true));
    }
}

if ('add_passkey' === Request::request('function', 'string')) {
    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } elseif (true !== $msg = $verifyLogin()) {
        $error = $msg;
    } else {
        [$passkeyId, $passkeyPublicKey] = $webauthn->processCreate($passkey);

        $sql = Sql::factory();
        $sql->setTable('rex_user_passkey');
        $sql->setValue('id', $passkeyId);
        $sql->setValue('user_id', $userId);
        $sql->setValue('public_key', $passkeyPublicKey);
        $sql->setDateTimeValue('createdate', time());
        $sql->insert();

        $success = I18n::msg('passkey_added');
    }
}

// ---------------------------------- ERR MSG

if ($passwordChangeRequired) {
    echo Message::warning(I18n::msg('password_change_required'));
}

if ('' != $success) {
    echo Message::success($success);
}

if ('' != $error) {
    echo Message::error($error);
}

echo ApiFunction::getMessage();

// --------------------------------- FORMS

if (!$passwordChangeRequired) {
    echo $profileForm->capture(static function (Form $form) use ($user): void { ?>
        <section class="rex-section" data-variant="edit">
            <header class="rex-section-header"><div class="rex-section-title"><?= Html::from($form->title ?? '') ?></div></header>
            <div class="rex-section-body">
                <?php foreach ($form->getFormErrors() as $error): ?>
                    <?= Message::error((string) Html::from($error)) ?>
                <?php endforeach ?>
                <?= new FieldRow(Html::raw('<p class="rex-field-value">' . escape($user->login) . '</p>'), new Translatable('login_name'))->render() ?>
                <?= $form->rest() ?>
            </div>
            <footer class="rex-section-footer">
                <div class="rex-form-panel-footer">
                    <?= $form->buttons() ?>
                </div>
            </footer>
        </section>
    <?php });
}

$passkeyVerify = $login->getPasskey() ? $webauthn->getGetArgs($login->getPasskey()) : '';
$confirmField = static function (string $id) use ($login, $passkeyVerify): string {
    $formElements = [];
    $n = [];

    if ($login->getPasskey()) {
        $n['label'] = '<label for="' . $id . '">' . I18n::msg('passkey_current') . '</label>';
        $n['field'] = '<div data-auth-passkey-verify="' . escape($passkeyVerify) . '">
        <button type="button" class="rex-button" data-variant="primary" id="' . $id . '">' . I18n::msg('passkey_current_verify') . '</button>
        <i class="fa-regular fa-circle-check rex-u-text-success rex-u-hidden" aria-hidden="true"></i>
        <input type="hidden" name="passkey_verify"/>
    </div>';
    } else {
        $n['label'] = '<label for="' . $id . '">' . I18n::msg('current_password') . '</label>';
        $n['field'] = '<input class="rex-input" type="password" id="' . $id . '" name="userpsw" autocomplete="current-password" autocorrect="off" autocapitalize="off" required />';
    }

    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('flush', true);
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);

    return $fragment->parse('core/form/form.php');
};

$content = '<fieldset>' . $confirmField('rex-id-userpsw');

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-id-userpsw-new-1">' . I18n::msg('new_password') . '</label>';
$n['field'] = '<input class="rex-input" type="password" id="rex-id-userpsw-new-1" name="userpsw_new_1" autocomplete="new-password" autocorrect="off" autocapitalize="off" required ' . Str::buildAttributes($passwordPolicy->getHtmlAttributes()) . ' />';
$n['note'] = $passwordPolicy->getDescription();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-userpsw-new-2">' . I18n::msg('new_password_repeat') . '</label>';
$n['field'] = '<input class="rex-input" type="password" id="rex-id-userpsw-new-2" name="userpsw_new_2" autocomplete="new-password" autocorrect="off" autocapitalize="off" required />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('flush', true);
$fragment->setVar('group', true);
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

$formElements = [];

$n = [];
$n['field'] = '<button class="rex-button rex-form-aligned" data-variant="success" type="submit" value="1" name="upd_psw_button" ' . Accesskey::attributes(I18n::msg('profile_save_psw'), 'save') . '>' . I18n::msg('profile_save_psw') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg($user->password ? 'profile_changepsw' : 'add_password'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$changePassword = $fragment->parse('core/page/section.php');

$content = '<fieldset>' . $confirmField('rex-id-user-passkey');
$content .= '<input type="hidden" name="passkey" data-auth-passkey="' . escape($webauthn->getCreateArgs()) . '"/>';
$content .= '</fieldset>';

$formElements = [];

$n = [];
$n['field'] = '<button class="rex-button rex-form-aligned" data-variant="success" type="submit" value="1" name="add_passkey" ' . Accesskey::attributes(I18n::msg('passkey_add'), 'save') . '>' . I18n::msg('passkey_add') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('passkey_add'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$addPasskey = $fragment->parse('core/page/section.php');

$content = '
    <div class="rex-grid">
        <div data-span="md:6">
            <form action="' . Url::currentBackendPage() . '" method="post" data-auth-change-password>
                ' . $csrfToken->getHiddenField() . '
                ' . $changePassword . '
            </form>
        </div>';
if (!$passwordChangeRequired) {
    $content .= '
        <div data-span="md:6">
            <form class="rex-u-hidden" action="' . Url::currentBackendPage() . '" method="post" data-auth-add-passkey>
                <input type="hidden" name="function" value="add_passkey"/>
                ' . $csrfToken->getHiddenField() . '
                ' . $addPasskey . '
            </form>
        </div>';
}
$content .= '
    </div>';

echo $content;

require Path::core('pages/profile/auth_methods.php');
require Path::core('pages/profile/sessions.php');
