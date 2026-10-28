<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\WebAuthn;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$loginMessage = BackendLogin::requireCurrent()->message;
$rexUserLogin = Request::post('rex_user_login', 'string');

echo View::title(I18n::msg('login'));

$content = '';

$fragment = new Fragment();
$content .= $fragment->parse('core/login_branding.php');

$js = '';
if ('' != $loginMessage) {
    $content .= '<div class="rex-js-login-message">' . Message::error($loginMessage) . '</div>';
    // the login stays disabled until the countdown of a blocked login has run out
    $js = '
        const output = document.querySelector(".rex-js-login-message strong[data-time]");
        if (output) {
            const fields = document.querySelectorAll("#rex-form-login :is(input, select, textarea, button):not([type=hidden])");
            const countdown = () => {
                const time = Number(output.dataset.time);
                output.dataset.time = String(time - 1);
                const hours = Math.floor(time / 3600);
                const mins = Math.floor((time - hours * 3600) / 60);
                const secs = time % 60;
                output.textContent = (hours ? hours + "h " : "") + (hours || mins ? mins + "min " : "") + secs + "s";
                if (time > 0) {
                    setTimeout(countdown, 1000);
                } else {
                    const message = document.querySelector(".rex-js-login-message .alert");
                    if (message) {
                        message.textContent = "' . escape(I18n::msg('login_welcome'), 'js') . '";
                    }
                    fields.forEach(field => field.disabled = false);
                    document.getElementById("rex-id-login-user").focus();
                }
            };
            fields.forEach(field => field.disabled = true);
            setTimeout(countdown, 1000);
        }';
}

$content .= '
    <fieldset>
';

$formElements = [];

$inputGroups = [];
$n = [];
$n['field'] = '<input class="form-control" type="text" value="' . escape($rexUserLogin) . '" id="rex-id-login-user" name="rex_user_login" autocomplete="username webauthn" inputmode="email" autocorrect="off" autocapitalize="off" autofocus />';
$n['left'] = '<i class="rex-icon rex-icon-user"></i>';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-id-login-user">' . I18n::msg('login_name') . ':</label>';
$n['field'] = $inputGroup;
$n['vertical'] = true;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['field'] = '<input class="form-control" type="password" name="rex_user_psw" id="rex-id-login-password" autocomplete="current-password" autocorrect="off" autocapitalize="off" />';
$n['left'] = '<i class="rex-icon rex-icon-password"></i>';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-id-login-password">' . I18n::msg('password') . ':</label>';
$n['field'] = $inputGroup;
$n['vertical'] = true;
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
if (BackendLogin::getLoginPolicy()->stayLoggedInEnabled) {
    $n = [];
    $n['label'] = '<label for="rex-id-login-stay-logged-in">' . I18n::msg('stay_logged_in') . '</label>';
    $n['field'] = '<input type="checkbox" name="rex_user_stay_logged_in" id="rex-id-login-stay-logged-in" value="1" />';
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$content .= '</fieldset>';

$formElements = [];
$n = [];
$n['field'] = '<button class="rex-button" data-variant="primary" data-width="full" type="submit"><i class="rex-icon rex-icon-sign-in"></i> ' . I18n::msg('login') . ' </button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$webauthn = new WebAuthn();

$content = '
<form id="rex-form-login" action="' . Url::backendController() . '" method="post" data-auth-login>
    ' . $content . '
    ' . CsrfToken::factory('backend_login')->getHiddenField() . '
    <input type="hidden" name="rex_user_passkey" data-auth-passkey="' . escape($webauthn->getGetArgs()) . '"/>
</form>
<script nonce="' . Response::getNonce() . '">
    {' . $js . '}
</script>';

$fragment = new Fragment();
$content .= $fragment->parse('core/login_background.php');

echo $content;
