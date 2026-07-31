<?php

/**
 * Addon Framework Classes.
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 */

$addon = rex_addon::get('phpmailer');

$message = '';

$csrfToken = rex_csrf_token::factory('phpmailer-config');
$submitted = '' != rex_post('btn_save', 'string') || '' != rex_post('btn_check', 'string');

if ($submitted && !$csrfToken->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
} elseif ($submitted) {
    $settings = rex_post('settings', [
        ['fromname', 'string'],
        ['from', 'string'],
        ['detour_mode', 'boolean'],
        ['confirmto', 'string'],
        ['bcc', 'string'],
        ['returnto', 'string'],
        ['mailer', 'string'],
        ['host', 'string'],
        ['port', 'int'],
        ['charset', 'string'],
        ['wordwrap', 'int'],
        ['encoding', 'string'],
        ['msgraph_client_id', 'string'],
        ['msgraph_client_secret', 'string'],
        ['msgraph_tenant_id', 'string'],
        ['username', 'string'],
        ['password', 'string'],
        ['smtpsecure', 'string'],
        ['security_mode', 'boolean'],
        ['smtpauth', 'boolean'],
        ['priority', 'int'],
        ['smtp_debug', 'int'],
        ['test_address', 'string'],
        ['logging', 'int'],
        ['archive', 'boolean'],
    ]);

    if (true == $settings['detour_mode'] && false == rex_validator::factory()->email($settings['test_address'])) {
        $settings['detour_mode'] = false;
        $warning = $addon->i18n('detour_warning');
        echo rex_view::warning($warning);
    }

    // Reset the token if the Microsoft 365 settings have changed
    if ('' != $addon->getConfig('msgraph_client_id')
        && $settings['msgraph_client_id'] . $settings['msgraph_client_secret'] . $settings['msgraph_tenant_id'] !=
        $addon->getConfig('msgraph_client_id') . $addon->getConfig('msgraph_client_secret') . $addon->getConfig('msgraph_tenant_id')) {
        $addon->setConfig('msgraph_token', '');
        echo rex_view::success($addon->i18n('phpmailer_msgraph_token_deleted'));
    }

    $addon->setConfig($settings);

    if ('' != rex_post('btn_check', 'string')) {
        if (false == rex_validator::factory()->email($settings['from']) || false == rex_validator::factory()->email($settings['test_address'])) {
            $warning = $addon->i18n('check_settings_not_tested');
            echo rex_view::warning($warning);
        } else {
            rex_response::sendRedirect(rex_url::backendPage('phpmailer/checkmail'));
        }
    }

    $message = $addon->i18n('config_saved_successful');
}

$selMailer = new rex_select();
$selMailer->setId('phpmailer-mailer');
$selMailer->setName('settings[mailer]');
$selMailer->setSize(1);
$selMailer->setAttribute('class', 'form-control selectpicker');
$selMailer->setSelected($addon->getConfig('mailer'));
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

$selSecurityMode = new rex_select();
$selSecurityMode->setId('security_mode');
$selSecurityMode->setName('settings[security_mode]');
$selSecurityMode->setSize(1);
$selSecurityMode->setAttribute('class', 'form-control selectpicker');
$selSecurityMode->setSelected($addon->getConfig('security_mode'));
foreach ([0 => $addon->i18n('security_mode_manual'), 1 => $addon->i18n('security_mode_auto')] as $i => $type) {
    $selSecurityMode->addOption($type, $i);
}

$selSmtpauth = new rex_select();
$selSmtpauth->setId('phpmailer-smtpauth');
$selSmtpauth->setName('settings[smtpauth]');
$selSmtpauth->setSize(1);
$selSmtpauth->setAttribute('class', 'form-control selectpicker');
$selSmtpauth->setSelected($addon->getConfig('smtpauth'));
foreach ([0 => $addon->i18n('disabled'), 1 => $addon->i18n('enabled')] as $i => $type) {
    $selSmtpauth->addOption($type, $i);
}

$selSmtpsecure = new rex_select();
$selSmtpsecure->setId('phpmailer-smtpsecure');
$selSmtpsecure->setName('settings[smtpsecure]');
$selSmtpsecure->setSize(1);
$selSmtpsecure->setAttribute('class', 'form-control selectpicker');
$selSmtpsecure->setSelected($addon->getConfig('smtpsecure'));
foreach (['' => $addon->i18n('no'), 'ssl' => 'ssl', 'tls' => 'tls'] as $type => $name) {
    $selSmtpsecure->addOption($name, $type);
}

$selEncoding = new rex_select();
$selEncoding->setId('phpmailer-encoding');
$selEncoding->setName('settings[encoding]');
$selEncoding->setSize(1);
$selEncoding->setAttribute('class', 'form-control selectpicker');
$selEncoding->setSelected($addon->getConfig('encoding'));
foreach (['7bit', '8bit', 'binary', 'base64', 'quoted-printable'] as $enc) {
    $selEncoding->addOption($enc, $enc);
}

$selPriority = new rex_select();
$selPriority->setid('phpmailer-priority');
$selPriority->setName('settings[priority]');
$selPriority->setSize(1);
$selPriority->setAttribute('class', 'form-control selectpicker');
$selPriority->setSelected($addon->getConfig('priority'));
foreach ([0 => $addon->i18n('disabled'), 1 => $addon->i18n('high'), 3 => $addon->i18n('normal'), 5 => $addon->i18n('low')] as $no => $name) {
    $selPriority->addOption($name, $no);
}

$selLog = new rex_select();
$selLog->setid('phpmailer-log');
$selLog->setName('settings[logging]');
$selLog->setSize(1);
$selLog->setAttribute('class', 'form-control selectpicker');
$selLog->setSelected($addon->getConfig('logging'));
$selLog->addOption($addon->i18n('log_no'), 0);
$selLog->addOption($addon->i18n('log_errors'), rex_mailer::LOG_ERRORS);
$selLog->addOption($addon->i18n('log_all'), rex_mailer::LOG_ALL);

$selArchive = new rex_select();
$selArchive->setid('phpmailer-archive');
$selArchive->setName('settings[archive]');
$selArchive->setSize(1);
$selArchive->setAttribute('class', 'form-control selectpicker');
$selArchive->setSelected((int) $addon->getConfig('archive'));
$selArchive->addOption($addon->i18n('log_no'), 0);
$selArchive->addOption($addon->i18n('log_yes'), 1);

$selDebug = new rex_select();
$selDebug->setid('phpmailer-smtp_debug');
$selDebug->setName('settings[smtp_debug]');
$selDebug->setSize(1);
$selDebug->setAttribute('class', 'form-control selectpicker');
$selDebug->setSelected($addon->getConfig('smtp_debug'));
foreach ([0 => $addon->i18n('smtp_debug_0'), 1 => $addon->i18n('smtp_debug_1'), 2 => $addon->i18n('smtp_debug_2'), 3 => $addon->i18n('smtp_debug_3'), 4 => $addon->i18n('smtp_debug_4')] as $no => $name) {
    $selDebug->addOption($name, $no);
}

if ('' != $message) {
    echo rex_view::success($message);
}

$content = '';
$content .= '<div class="row">';
$content .= '<div class="col-sm-6">';
$content .= '<fieldset><legend>' . $addon->i18n('email_options') . '</legend>';

$formElements = [];
$n = [];
$n['label'] = '<label for="phpmailer-fromname">' . $addon->i18n('sender_name') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-fromname" type="text" name="settings[fromname]" value="' . rex_escape($addon->getConfig('fromname')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-from">' . $addon->i18n('sender_email') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-from" type="email" name="settings[from]" placeholder="name@example.tld" value="' . rex_escape($addon->getConfig('from')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-testaddress">' . $addon->i18n('checkmail_test_address') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-testaddress" type="email" name="settings[test_address]" placeholder="test@example.tld" value="' . rex_escape($addon->getConfig('test_address')) . '" />';
$formElements[] = $n;

$selDetourMode = new rex_select();
$selDetourMode->setId('phpmailer-detour-mode');
$selDetourMode->setName('settings[detour_mode]');
$selDetourMode->setSize(1);
$selDetourMode->setAttribute('class', 'form-control selectpicker');

$selDetourMode->setSelected($addon->getConfig('detour_mode') ?: 0);
foreach ([$addon->i18n('disabled'), $addon->i18n('enabled')] as $key => $value) {
    $selDetourMode->addOption($value, $key);
}

$detourModeLabelClass = $addon->getConfig('detour_mode') ? 'text-danger' : '';

$n = [];
$n['label'] = '<label for="phpmailer-detour-mode" class="' . $detourModeLabelClass . '">' . $addon->i18n('detour_email_redirect') . '</label>';
$n['field'] = $selDetourMode->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-confirmto">' . $addon->i18n('confirm') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-confirmto" type="email" name="settings[confirmto]" placeholder="confirm@example.tld" value="' . rex_escape($addon->getConfig('confirmto')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-bcc">' . $addon->i18n('bcc') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-bcc" type="email" name="settings[bcc]" placeholder="bcc@example.tld" value="' . rex_escape($addon->getConfig('bcc')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-returnto">' . $addon->i18n('returnto_email') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-returnto" type="email" name="settings[returnto]" placeholder="returnto@example.tld" value="' . rex_escape($addon->getConfig('returnto')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-mailer">' . $addon->i18n('mailertype') . '</label>';
$n['field'] = $selMailer->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];

$content .= '</fieldset>';
$content .= '<fieldset id="microsoftsettings" data-toggle="tooltip" title="' . $addon->i18n('phpmailer_microsoft365_info') . '"><legend>' . $addon->i18n('microsoft_options') . ' <i class="rex-icon fa-question-circle"></i></legend>';

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_tenant_id">' . $addon->i18n('msgraph_tenant_id') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_tenant_id" type="text" name="settings[msgraph_tenant_id]" value="' . rex_escape($addon->getConfig('msgraph_tenant_id')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_client_id">' . $addon->i18n('msgraph_client_id') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_client_id" type="text" name="settings[msgraph_client_id]" value="' . rex_escape($addon->getConfig('msgraph_client_id')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-msgraph_client_secret">' . $addon->i18n('msgraph_client_secret') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-msgraph_client_secret" type="text" name="settings[msgraph_client_secret]" value="' . rex_escape($addon->getConfig('msgraph_client_secret')) . '" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';
$content .= '<fieldset id="smtpsettings"><legend>' . $addon->i18n('smtp_options') . '</legend>';

$formElements = [];
$n = [];
$n['label'] = '<label for="phpmailer-host">' . $addon->i18n('host') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-host" placeholder="smtp.example.tld" type="text" name="settings[host]" value="' . rex_escape($addon->getConfig('host')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-port">' . $addon->i18n('port') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-port" type="number" name="settings[port]" value="' . rex_escape($addon->getConfig('port')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label data-toggle="tooltip" title="' . $addon->i18n('security_mode_help') . '" for="security_mode">' . rex_escape($addon->i18n('security_mode')) . ' <i class="rex-icon fa-question-circle"></i></label>';
$n['field'] = $selSecurityMode->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$content .= '<div id="securetype">';
$n = [];
$n['label'] = '<label for="phpmailer-smtpsecure">' . $addon->i18n('smtp_secure') . '</label>';
$n['field'] = $selSmtpsecure->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '</div>';

$n = [];
$n['label'] = '<label for="phpmailer-smtpauth">' . $addon->i18n('smtp_auth') . '</label>';
$n['field'] = $selSmtpauth->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '<div id="smtpauthlogin">';

$n = [];
$n['label'] = '<label for="phpmailer-username">' . $addon->i18n('smtp_username') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-username" type="text" name="settings[username]" value="' . rex_escape($addon->getConfig('username')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-password">' . $addon->i18n('smtp_password') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-password" type="password" name="settings[password]" value="' . rex_escape($addon->getConfig('password')) . '" autocomplete="new-password" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$formElements = [];
$content .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset></div>';
$content .= '<fieldset class="col-sm-6"><legend>' . $addon->i18n('dispatch_options') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for="phpmailer-charset">' . $addon->i18n('charset') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-charset" type="text" name="settings[charset]" value="' . rex_escape($addon->getConfig('charset')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-wordwrap">' . $addon->i18n('wordwrap') . '</label>';
$n['field'] = '<input class="form-control" id="phpmailer-wordwrap" type="number" name="settings[wordwrap]" value="' . rex_escape($addon->getConfig('wordwrap')) . '" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-encoding">' . $addon->i18n('encoding') . '</label>';
$n['field'] = $selEncoding->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-priority">' . $addon->i18n('priority') . '</label>';
$n['field'] = $selPriority->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-smtp_debug">' . $addon->i18n('smtp_debug') . '</label>';
$n['field'] = $selDebug->get() . '<p class="help-block rex-note"> ' . $addon->i18n('smtp_debug_info') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="phpmailer-log">' . $addon->i18n('logging') . '</label>';
$n['field'] = $selLog->get();
$formElements[] = $n;

$n = [];
$n = [];
$n['label'] = '<label for="phpmailer-archive">' . $addon->i18n('archive') . '</label>';
$n['field'] = $selArchive->get();
if ($addon->getConfig('archive')) {
    $n['note'] = rex_i18n::rawMsg('phpmailer_archive_info', rex_mailer::logFolder(), '...' . substr(rex_mailer::logFolder(), -30))
               . '<br><a href="' . rex_url::backendPage('phpmailer/archive') . '" class="btn btn-sm btn-default">'
               . '<i class="rex-icon rex-icon-folder-open"></i> ' . $addon->i18n('archive_manage') . '</a>';
} else {
    $n['note'] = $addon->i18n('archive_disabled_note');
}
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset></div>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-reset pull-right" type="reset" name="btn_reset" value="' . $addon->i18n('reset') . '" data-confirm="' . $addon->i18n('reset_info') . '">' . $addon->i18n('reset') . '</button>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-save pull-right" type="submit" name="btn_check" value="' . $addon->i18n('check_settings_btn') . '">' . $addon->i18n('check_settings_btn') . '</button>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-save pull-right" type="submit" name="btn_save" value="' . $addon->i18n('save') . '">' . $addon->i18n('save') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('config_settings'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');
echo '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        ' . $content . '
    </form>';
?>
<script nonce="<?= rex_response::getNonce() ?>">
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

