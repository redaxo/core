<?php

use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\Validator;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$message = '';

if ('' != Request::post('btn_save', 'string') || '' != Request::post('btn_check', 'string')) {
    $settings = Request::post('settings', [
        ['phpmailer_fromname', 'string'],
        ['phpmailer_from', 'string'],
        ['phpmailer_detour_mode', 'boolean'],
        ['phpmailer_confirmto', 'string'],
        ['phpmailer_bcc', 'string'],
        ['phpmailer_returnto', 'string'],
        ['phpmailer_mailer', 'string'],
        ['phpmailer_host', 'string'],
        ['phpmailer_port', 'int'],
        ['phpmailer_charset', 'string'],
        ['phpmailer_wordwrap', 'int'],
        ['phpmailer_encoding', 'string'],
        ['phpmailer_msgraph_client_id', 'string'],
        ['phpmailer_msgraph_client_secret', 'string'],
        ['phpmailer_msgraph_tenant_id', 'string'],
        ['phpmailer_username', 'string'],
        ['phpmailer_password', 'string'],
        ['phpmailer_smtpsecure', 'string'],
        ['phpmailer_security_mode', 'boolean'],
        ['phpmailer_smtpauth', 'boolean'],
        ['phpmailer_priority', 'int'],
        ['phpmailer_smtp_debug', 'int'],
        ['phpmailer_test_address', 'string'],
        ['phpmailer_logging', 'int'],
        ['phpmailer_archive', 'boolean'],
    ]);

    if (true == $settings['phpmailer_detour_mode'] && false == Validator::factory()->email($settings['phpmailer_test_address'])) {
        $settings['phpmailer_detour_mode'] = false;
        $warning = I18n::msg('phpmailer_detour_warning');
        echo Message::warning($warning);
    }

    // Reset the token if the Microsoft 365 settings have changed
    if ('' != Core::getConfig('phpmailer_msgraph_client_id')
        && $settings['phpmailer_msgraph_client_id'] . $settings['phpmailer_msgraph_client_secret'] . $settings['phpmailer_msgraph_tenant_id'] !=
        Core::getConfig('phpmailer_msgraph_client_id') . Core::getConfig('phpmailer_msgraph_client_secret') . Core::getConfig('phpmailer_msgraph_tenant_id')) {
        Core::setConfig('phpmailer_msgraph_token', '');
        echo Message::success(I18n::msg('phpmailer_msgraph_token_deleted'));
    }

    Config::set('core', $settings);

    if ('' != Request::post('btn_check', 'string')) {
        if (false == Validator::factory()->email($settings['phpmailer_from']) || false == Validator::factory()->email($settings['phpmailer_test_address'])) {
            $warning = I18n::msg('phpmailer_check_settings_not_tested');
            echo Message::warning($warning);
        } else {
            Response::sendRedirect(Url::backendPage('phpmailer/checkmail'));
        }
    }

    $message = I18n::msg('phpmailer_config_saved_successful');
}

$selMailer = new Select();
$selMailer->setId('phpmailer-mailer');
$selMailer->setName('settings[phpmailer_mailer]');
$selMailer->setSize(1);
$selMailer->setAttribute('class', 'form-control selectpicker');
$selMailer->setSelected(Core::getConfig('phpmailer_mailer'));
$mta = [];
if (function_exists('mail')) {
    $mta[] = 'mail';
}
$mta[] = 'smtp';
$mta[] = 'sendmail';
$mta[] = 'microsoft365';
foreach ($mta as $type) {
    $selMailer->addOption($type, $type);
}

$selSecurityMode = new Select();
$selSecurityMode->setId('security_mode');
$selSecurityMode->setName('settings[phpmailer_security_mode]');
$selSecurityMode->setSize(1);
$selSecurityMode->setAttribute('class', 'form-control selectpicker');
$selSecurityMode->setSelected(Core::getConfig('phpmailer_security_mode'));
foreach ([0 => I18n::msg('phpmailer_security_mode_manual'), 1 => I18n::msg('phpmailer_security_mode_auto')] as $i => $type) {
    $selSecurityMode->addOption($type, $i);
}

$selSmtpauth = new Select();
$selSmtpauth->setId('phpmailer-smtpauth');
$selSmtpauth->setName('settings[phpmailer_smtpauth]');
$selSmtpauth->setSize(1);
$selSmtpauth->setAttribute('class', 'form-control selectpicker');
$selSmtpauth->setSelected(Core::getConfig('phpmailer_smtpauth'));
foreach ([0 => I18n::msg('phpmailer_disabled'), 1 => I18n::msg('phpmailer_enabled')] as $i => $type) {
    $selSmtpauth->addOption($type, $i);
}

$selSmtpsecure = new Select();
$selSmtpsecure->setId('phpmailer-smtpsecure');
$selSmtpsecure->setName('settings[phpmailer_smtpsecure]');
$selSmtpsecure->setSize(1);
$selSmtpsecure->setAttribute('class', 'form-control selectpicker');
$selSmtpsecure->setSelected(Core::getConfig('phpmailer_smtpsecure'));
foreach (['' => I18n::msg('phpmailer_no'), 'ssl' => 'ssl', 'tls' => 'tls'] as $type => $name) {
    $selSmtpsecure->addOption($name, $type);
}

$selEncoding = new Select();
$selEncoding->setId('phpmailer-encoding');
$selEncoding->setName('settings[phpmailer_encoding]');
$selEncoding->setSize(1);
$selEncoding->setAttribute('class', 'form-control selectpicker');
$selEncoding->setSelected(Core::getConfig('phpmailer_encoding'));
foreach (['7bit', '8bit', 'binary', 'base64', 'quoted-printable'] as $enc) {
    $selEncoding->addOption($enc, $enc);
}

$selPriority = new Select();
$selPriority->setId('phpmailer-priority');
$selPriority->setName('settings[phpmailer_priority]');
$selPriority->setSize(1);
$selPriority->setAttribute('class', 'form-control selectpicker');
$selPriority->setSelected(Core::getConfig('phpmailer_priority'));
foreach ([0 => I18n::msg('phpmailer_disabled'), 1 => I18n::msg('phpmailer_high'), 3 => I18n::msg('phpmailer_normal'), 5 => I18n::msg('phpmailer_low')] as $no => $name) {
    $selPriority->addOption($name, $no);
}

$selLog = new Select();
$selLog->setId('phpmailer-log');
$selLog->setName('settings[phpmailer_logging]');
$selLog->setSize(1);
$selLog->setAttribute('class', 'form-control selectpicker');
$selLog->setSelected(Core::getConfig('phpmailer_logging'));
$selLog->addOption(I18n::msg('phpmailer_log_no'), 0);
$selLog->addOption(I18n::msg('phpmailer_log_errors'), Mailer::LOG_ERRORS);
$selLog->addOption(I18n::msg('phpmailer_log_all'), Mailer::LOG_ALL);

$selArchive = new Select();
$selArchive->setId('phpmailer-archive');
$selArchive->setName('settings[phpmailer_archive]');
$selArchive->setSize(1);
$selArchive->setAttribute('class', 'form-control selectpicker');
$selArchive->setSelected((int) Core::getConfig('phpmailer_archive'));
$selArchive->addOption(I18n::msg('phpmailer_log_no'), 0);
$selArchive->addOption(I18n::msg('phpmailer_log_yes'), 1);

$selDebug = new Select();
$selDebug->setId('phpmailer-smtp_debug');
$selDebug->setName('settings[phpmailer_smtp_debug]');
$selDebug->setSize(1);
$selDebug->setAttribute('class', 'form-control selectpicker');
$selDebug->setSelected(Core::getConfig('phpmailer_smtp_debug'));
foreach ([0 => I18n::msg('phpmailer_smtp_debug_0'), 1 => I18n::msg('phpmailer_smtp_debug_1'), 2 => I18n::msg('phpmailer_smtp_debug_2'), 3 => I18n::msg('phpmailer_smtp_debug_3'), 4 => I18n::msg('phpmailer_smtp_debug_4')] as $no => $name) {
    $selDebug->addOption($name, $no);
}

if ('' != $message) {
    echo Message::success($message);
}

$content = '';
$content .= '<div class="row">';
$content .= '<div class="col-sm-6">';
$content .= '<fieldset><legend>' . I18n::msg('phpmailer_email_options') . '</legend>';

$formElements = [];
$n = [];
$n['label'] = '<label for="phpmailer-fromname">' . I18n::msg('phpmailer_sender_name') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-fromname" type="text" name="settings[phpmailer_fromname]" value="' . escape(Core::getConfig('phpmailer_fromname')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-from">' . I18n::msg('phpmailer_sender_email') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-from" type="email" name="settings[phpmailer_from]" placeholder="name@example.tld" value="' . escape(Core::getConfig('phpmailer_from')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-testaddress">' . I18n::msg('phpmailer_checkmail_test_address') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-testaddress" type="email" name="settings[phpmailer_test_address]" placeholder="test@example.tld" value="' . escape(Core::getConfig('phpmailer_test_address')) . '" />';
$formElements[] = $n;

$selDetourMode = new Select();
$selDetourMode->setId('phpmailer-detour-mode');
$selDetourMode->setName('settings[phpmailer_detour_mode]');
$selDetourMode->setSize(1);
$selDetourMode->setAttribute('class', 'form-control selectpicker');

$selDetourMode->setSelected(Core::getConfig('phpmailer_detour_mode') ?: 0);
foreach ([I18n::msg('phpmailer_disabled'), I18n::msg('phpmailer_enabled')] as $key => $value) {
    $selDetourMode->addOption($value, $key);
}

$detourModeLabelClass = Core::getConfig('phpmailer_detour_mode') ? 'text-danger' : '';

$n = [];
$n['label'] = '<label for="phpmailer-detour-mode" class="' . $detourModeLabelClass . '">' . I18n::msg('phpmailer_detour_email_redirect') . '</label>';
$n['field'] = $selDetourMode->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-confirmto">' . I18n::msg('phpmailer_confirm') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-confirmto" type="email" name="settings[phpmailer_confirmto]" placeholder="confirm@example.tld" value="' . escape(Core::getConfig('phpmailer_confirmto')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-bcc">' . I18n::msg('phpmailer_bcc') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-bcc" type="email" name="settings[phpmailer_bcc]" placeholder="bcc@example.tld" value="' . escape(Core::getConfig('phpmailer_bcc')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-returnto">' . I18n::msg('phpmailer_returnto_email') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-returnto" type="email" name="settings[phpmailer_returnto]" placeholder="returnto@example.tld" value="' . escape(Core::getConfig('phpmailer_returnto')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-mailer">' . I18n::msg('phpmailer_mailertype') . '</label>';
$n['field'] = $selMailer->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];

$content .= '</fieldset>';
$content .= '<fieldset id="microsoftsettings" data-toggle="tooltip" title="' . I18n::msg('phpmailer_microsoft365_info') . '"><legend>' . I18n::msg('phpmailer_microsoft_options') . ' <i class="rex-icon fa-question-circle"></i></legend>';

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_tenant_id">' . I18n::msg('phpmailer_msgraph_tenant_id') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_tenant_id" type="text" name="settings[phpmailer_msgraph_tenant_id]" value="' . escape(Core::getConfig('phpmailer_msgraph_tenant_id')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_client_id">' . I18n::msg('phpmailer_msgraph_client_id') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_client_id" type="text" name="settings[phpmailer_msgraph_client_id]" value="' . escape(Core::getConfig('phpmailer_msgraph_client_id')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_client_secret">' . I18n::msg('phpmailer_msgraph_client_secret') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_client_secret" type="text" name="settings[phpmailer_msgraph_client_secret]" value="' . escape(Core::getConfig('phpmailer_msgraph_client_secret')) . '" />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';
$content .= '<fieldset id="smtpsettings"><legend>' . I18n::msg('phpmailer_smtp_options') . '</legend>';

$formElements = [];
$n = [];
$n['label'] = '<label for="phpmailer-host">' . I18n::msg('phpmailer_host') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-host" placeholder="smtp.example.tld" type="text" name="settings[phpmailer_host]" value="' . escape(Core::getConfig('phpmailer_host')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-port">' . I18n::msg('phpmailer_port') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-port" type="number" name="settings[phpmailer_port]" value="' . escape(Core::getConfig('phpmailer_port')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label data-toggle="tooltip" title="' . I18n::msg('phpmailer_security_mode_help') . '" for="security_mode">' . escape(I18n::msg('phpmailer_security_mode')) . ' <i class="rex-icon fa-question-circle"></i></label>';
$n['field'] = $selSecurityMode->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$content .= '<div id="securetype">';
$n = [];
$n['label'] = '<label for="phpmailer-smtpsecure">' . I18n::msg('phpmailer_smtp_secure') . '</label>';
$n['field'] = $selSmtpsecure->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '</div>';

$n = [];
$n['label'] = '<label for="phpmailer-smtpauth">' . I18n::msg('phpmailer_smtp_auth') . '</label>';
$n['field'] = $selSmtpauth->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '<div id="smtpauthlogin">';

$n = [];
$n['label'] = '<label for="phpmailer-username">' . I18n::msg('phpmailer_smtp_username') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-username" type="text" name="settings[phpmailer_username]" value="' . escape(Core::getConfig('phpmailer_username')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-password">' . I18n::msg('phpmailer_smtp_password') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-password" type="password" name="settings[phpmailer_password]" value="' . escape(Core::getConfig('phpmailer_password')) . '" autocomplete="new-password" />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '</div>';

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset></div>';
$content .= '<fieldset class="col-sm-6"><legend>' . I18n::msg('phpmailer_dispatch_options') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for="phpmailer-charset">' . I18n::msg('phpmailer_charset') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-charset" type="text" name="settings[phpmailer_charset]" value="' . escape(Core::getConfig('phpmailer_charset')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-wordwrap">' . I18n::msg('phpmailer_wordwrap') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-wordwrap" type="number" name="settings[phpmailer_wordwrap]" value="' . escape(Core::getConfig('phpmailer_wordwrap')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-encoding">' . I18n::msg('phpmailer_encoding') . '</label>';
$n['field'] = $selEncoding->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-priority">' . I18n::msg('phpmailer_priority') . '</label>';
$n['field'] = $selPriority->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-smtp_debug">' . I18n::msg('phpmailer_smtp_debug') . '</label>';
$n['field'] = $selDebug->get() . '<p class="help-block rex-note"> ' . I18n::msg('phpmailer_smtp_debug_info') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-log">' . I18n::msg('phpmailer_logging') . '</label>';
$n['field'] = $selLog->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-archive">' . I18n::msg('phpmailer_archive') . '</label>';
$n['field'] = $selArchive->get();
if (Core::getConfig('phpmailer_archive')) {
    $n['note'] = I18n::rawMsg('phpmailer_archive_info', Mailer::logFolder(), '...' . substr(Mailer::logFolder(), -30))
        . '<br><a href="' . Url::backendPage('phpmailer/archive') . '" class="btn btn-sm btn-default">'
        . '<i class="rex-icon rex-icon-folder-open"></i> ' . I18n::msg('phpmailer_archive_manage') . '</a>';
} else {
    $n['note'] = I18n::msg('phpmailer_archive_disabled_note');
}
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset></div>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-reset pull-right" type="reset" name="btn_reset" value="' . I18n::msg('phpmailer_reset') . '" data-confirm="' . I18n::msg('phpmailer_reset_info') . '">' . I18n::msg('phpmailer_reset') . '</button>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-save pull-right" type="submit" name="btn_check" value="' . I18n::msg('phpmailer_check_settings_btn') . '">' . I18n::msg('phpmailer_check_settings_btn') . '</button>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-save pull-right" type="submit" name="btn_save" value="' . I18n::msg('phpmailer_save') . '">' . I18n::msg('phpmailer_save') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('phpmailer_config_settings'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');
echo '
    <form action="' . Url::currentBackendPage() . '" method="post">
        ' . $content . '
    </form>';
?>
<script nonce="<?= Response::getNonce() ?>">
    $('#smtpsettings').toggle(
        $('#phpmailer-mailer').find("option[value='smtp']").is(":checked")
    );
    $('#microsoftsettings').toggle(
        $('#phpmailer-mailer').find("option[value='microsoft365']").is(":checked")
    );
    $('#securetype').toggle(
        $('#security_mode').find("option[value='0']").is(":checked")
    );

    $('#smtpauthlogin').toggle(
        $('#phpmailer-smtpauth').find("option[value='1']").is(":checked")
    );

    $('#phpmailer-mailer').change(function(){
        if ($(this).val() == 'smtp') {
            $('#smtpsettings').slideDown();
        } else {
            $('#smtpsettings').slideUp();
        }
    });

    $('#phpmailer-mailer').change(function(){
        if ($(this).val() == 'microsoft365') {
            $('#microsoftsettings').slideDown();
        } else {
            $('#microsoftsettings').slideUp();
        }
    });

    $('#security_mode').change(function(){
        if ($(this).val() == '0') {
            $('#securetype').slideDown();
        } else {
            $('#securetype').slideUp();
        }
    });

    $('#phpmailer-smtpauth').change(function(){
        if ($(this).val() == '1') {
            $('#smtpauthlogin').slideDown();
        } else {
            $('#smtpauthlogin').slideUp();
        }
    });

</script>
