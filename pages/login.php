<?php

use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\WebAuthn;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

global $rexUserLoginmessage;

$rexUserLogin = Request::post('rex_user_login', 'string');

echo View::title(I18n::msg('login'));

$content = '';

$fragment = new Fragment();
$content .= $fragment->parse('core/login_branding.php');

$js = '';
if ('' != $rexUserLoginmessage) {
    $content .= '<div class="rex-js-login-message">' . Message::error($rexUserLoginmessage) . '</div>';
    $js = '
        var time_el = $(".rex-js-login-message strong[data-time]");
        if(time_el.length == 1) {
            function disableLogin() {
                var time = time_el.attr("data-time");
                time_el.attr("data-time", time - 1);
                var hours = Math.floor(time / 3600);
                var mins  = Math.floor((time - (hours * 3600)) / 60);
                var secs  = time % 60;
                var formatted = (hours ? hours + "h " : "") + (hours || mins ? mins + "min " : "") + secs + "s";
                time_el.html(formatted);
                if(time > 0) {
                    setTimeout(disableLogin, 1000);
                } else {
                    $("div.rex-message div").html("' . I18n::msg('login_welcome') . '");
                    $("#rex-form-login").find(":input:not(:hidden)").prop("disabled", "");
                    $("#rex-id-login-user").focus();
                }
            };
            $("#rex-form-login").find(":input:not(:hidden)").prop("disabled", "disabled");
            setTimeout(disableLogin, 1000);
        }';
}

$content .= '
    <fieldset>
        <input type="hidden" name="javascript" value="0" id="javascript" />';

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
$n['class'] = 'rex-form-group-vertical';
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
$n['class'] = 'rex-form-group-vertical';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
if (Core::getProperty('login')->getLoginPolicy()->isStayLoggedInEnabled()) {
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
$n['field'] = '<button class="btn btn-primary btn-block" type="submit"><i class="rex-icon rex-icon-sign-in"></i> ' . I18n::msg('login') . ' </button>';
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
<script type="text/javascript" nonce="' . Response::getNonce() . '">
     <!--
    jQuery(function($) {
        $("#rex-form-login")
            .submit(function(){
                var pwInp = $("#rex-id-login-password");
                if(pwInp.val() != "") {
                    $("#rex-form-login").append(\'<input type="hidden" name="\'+pwInp.attr("name")+\'" value="\'+Sha1.hash(pwInp.val())+\'" />\');
                }
        });

        $("#javascript").val("1");
        ' . $js . '
    });
     //-->
</script>';

$fragment = new Fragment();
$content .= $fragment->parse('core/login_background.php');

echo $content;
