<?php

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

assert(isset($context) && $context instanceof Context);
assert(isset($errorArray) && is_array($errorArray));
assert(isset($config) && is_array($config));
assert(isset($cancelSetupBtn));

$configFile = Path::coreData('config.yml');
$headline = View::title(I18n::msg('setup_300', Path::relative($configFile)) . $cancelSetupBtn);

$content = '';

$submitMessage = I18n::msg('setup_310');
if (count($errorArray) > 0) {
    $submitMessage = I18n::msg('setup_314');
}

$content .= '
            <fieldset>';

$timezoneSel = new Select();
$timezoneSel->setId('rex-form-timezone');
$timezoneSel->setStyle('class="form-control selectpicker"');
$timezoneSel->setAttribute('data-live-search', 'true');
$timezoneSel->setName('timezone');
$timezoneSel->setSize(1);
$timezoneSel->addOptions(DateTimeZone::listIdentifiers(), true);
$timezoneSel->setSelected($config['timezone']);

$dbCreateChecked = Request::post('redaxo_db_create', 'boolean') ? ' checked="checked"' : '';

/** @var array<string, string|bool|null> $dbConfig */
$dbConfig = $config['db'][1] ?? [];

$sslToggleChecked = '';
if (($dbConfig['ssl_ca'] ?? false)
    || ($dbConfig['ssl_key'] ?? false)
    || ($dbConfig['ssl_cert'] ?? false)
) {
    $sslToggleChecked = ' checked="checked"';
}

$sslCaMode = '';
$sslCaFile = '';
if ($dbConfig['ssl_ca'] ?? false) {
    if (true === $dbConfig['ssl_ca']) {
        $sslCaMode = 'system';
    } elseif (is_string($dbConfig['ssl_ca'])) {
        $sslCaMode = 'file';
        $sslCaFile = $dbConfig['ssl_ca'];
    }
}

$sslKey = (string) ($dbConfig['ssl_key'] ?? '');
$sslCert = (string) ($dbConfig['ssl_cert'] ?? '');
$sslVerifyServerCert = (bool) ($dbConfig['ssl_verify_server_cert'] ?? true);

$httpsRedirectSel = new Select();
$httpsRedirectSel->setId('rex-form-https');
$httpsRedirectSel->setStyle('class="form-control selectpicker"');
$httpsRedirectSel->setName('use_https');
$httpsRedirectSel->setSize(1);
$httpsRedirectSel->addArrayOptions(['false' => I18n::msg('https_disable'), 'backend' => I18n::msg('https_only_backend'), 'frontend' => I18n::msg('https_only_frontend'), 'true' => I18n::msg('https_activate')]);
$httpsRedirectSel->setSelected(true === $config['use_https'] ? 'true' : $config['use_https']);

// If the setup is called over http disable https options to prevent user from being locked out
if (!Request::isHttps()) {
    $httpsRedirectSel->setAttribute('disabled', 'disabled');
}

$content .= '<legend>' . I18n::msg('setup_302') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-form-serveraddress" class="required">' . I18n::msg('server') . '</label>';
$n['field'] = '<input class="form-control" type="url" id="rex-form-serveraddress" name="serveraddress" value="' . escape($config['server']) . '" required autofocus />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-servername" class="required">' . I18n::msg('servername') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-servername" name="servername" value="' . escape($config['servername']) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-error-email" class="required">' . I18n::msg('error_email') . '</label>';
$n['field'] = '<input class="form-control" type="email" id="rex-form-error-email" name="error_email" value="' . escape($config['error_email']) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-timezone" class="required">' . I18n::msg('setup_312') . '</label>';
$n['field'] = $timezoneSel->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset><fieldset><legend>' . I18n::msg('setup_303') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for=rex-form-mysql-host" class="required">MySQL Host</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-mysql-host" name="mysql_host" value="' . escape((string) ($dbConfig['host'] ?? '')) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-db-user-login" class="required">Login</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-db-user-login" name="redaxo_db_user_login" value="' . escape((string) ($dbConfig['login'] ?? '')) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-db-user-pass" class="required">' . I18n::msg('setup_309') . '</label>';
$n['field'] = '<input class="form-control" type="password" id="rex-form-db-user-pass" name="redaxo_db_user_pass" value="' . Setup::DEFAULT_DUMMY_PASSWORD . '" />';
$formElements[] = $n;

$n = [];
$n['field'] = '<p>' . I18n::msg('setup_password_hint') . '</p>';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-dbname" class="required">' . I18n::msg('setup_308') . '</label>';
$n['field'] = '<input class="form-control" type="text" value="' . escape((string) ($dbConfig['name'] ?? '')) . '" id="rex-form-dbname" name="dbname" required />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// SSL Configuration Section
$formElements = [];

// SSL Toggle Checkbox
$n = [];
$n['label'] = '<label>' . I18n::msg('setup_database_ssl') . '</label>';
$n['field'] = '<input type="checkbox" data-ssl-toggle="true" name="db_ssl_toggle" value="1"' . $sslToggleChecked . ' />';
$n['note'] = I18n::msg('setup_database_ssl_info');
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

// SSL Configuration Fields (collapsible)
$content .= '<div id="ssl-config-panel" style="display: none;">';

$sslFormElements = [];

// SSL CA Configuration
$sslCaSel = new Select();
$sslCaSel->setId('rex-form-ssl-ca-mode');
$sslCaSel->setStyle('class="form-control selectpicker"');
$sslCaSel->setName('db_ssl_ca_mode');
$sslCaSel->setSize(1);
$sslCaSel->addOption(I18n::msg('setup_ssl_ca_none'), '');
$sslCaSel->addOption(I18n::msg('setup_ssl_ca_system'), 'system');
$sslCaSel->addOption(I18n::msg('setup_ssl_ca_file'), 'file');
$sslCaSel->setSelected($sslCaMode);

$n = [];
$n['label'] = '<label for="rex-form-ssl-ca-mode">' . I18n::msg('setup_ssl_ca') . '</label>';
$n['field'] = $sslCaSel->get();
$sslFormElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-ssl-ca-file">' . I18n::msg('setup_ssl_ca_file_path') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-ssl-ca-file" name="db_ssl_ca_file" value="' . escape($sslCaFile) . '" placeholder="/path/to/ca.pem" data-ssl-field="file" />';
$sslFormElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-ssl-key">' . I18n::msg('setup_ssl_key') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-ssl-key" name="db_ssl_key" value="' . escape($sslKey) . '" placeholder="/path/to/key.pem" />';
$sslFormElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-ssl-cert">' . I18n::msg('setup_ssl_cert') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-ssl-cert" name="db_ssl_cert" value="' . escape($sslCert) . '" placeholder="/path/to/cert.pem" />';
$sslFormElements[] = $n;

// SSL verify server cert checkbox (separate for proper styling)
$sslVerifyFormElements = [];
$n = [];
$n['label'] = '<label>' . I18n::msg('setup_ssl_verify') . '</label>';
$sslVerifyChecked = $sslVerifyServerCert ? ' checked="checked"' : '';
$n['field'] = '<input type="checkbox" id="rex-form-ssl-verify" name="db_ssl_verify_server_cert" value="1"' . $sslVerifyChecked . ' />';
$sslVerifyFormElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $sslFormElements, false);
$content .= $fragment->parse('core/form/form.php');

$fragment = new Fragment();
$fragment->setVar('elements', $sslVerifyFormElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$content .= '</div>'; // End SSL config panel

// Database Create Checkbox
$formElements = [];
$n = [];
$n['label'] = '<label>' . I18n::msg('setup_311') . '</label>';
$n['field'] = '<input type="checkbox" name="redaxo_db_create" value="1"' . $dbCreateChecked . ' />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$content .= '</fieldset><fieldset><legend>' . I18n::msg('setup_security') . '</legend>';

$formElements = [];

if (!Request::isHttps()) {
    $n = [];
    $n['field'] = '<label class="form-control-static"><i class="fa fa-warning"></i> ' . I18n::msg('https_only_over_https') . '</label>';
    $formElements[] = $n;
}

$n = [];
$n['label'] = '<label>' . I18n::msg('https_activate_redirect_for') . '</label>';
$n['field'] = $httpsRedirectSel->get();
$formElements[] = $n;

$n = [];
$n['field'] = '<p>' . I18n::rawMsg('hsts_more_information') . '</p>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-setup" type="submit" value="' . I18n::msg('system_update') . '">' . $submitMessage . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

echo $headline;
echo implode('', $errorArray);

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('setup_316'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '<form action="' . $context->getUrl(['step' => 4]) . '" method="post">' . $content . '</form>';

?>
<script type="text/javascript" nonce="<?= Response::getNonce() ?>">
jQuery(document).ready(function($) {
    // SSL Panel Toggle
    $('input[data-ssl-toggle="true"]').on('change', function() {
        var panel = $('#ssl-config-panel');
        if ($(this).is(':checked')) {
            panel.slideDown(300);
        } else {
            panel.slideUp(300);
        }
    });

    // Show SSL panel if checkbox is checked (from POST data or config)
    if ($('input[data-ssl-toggle="true"]').is(':checked')) {
        $('#ssl-config-panel').show();
    }

    // SSL CA file field visibility
    function toggleSslCaFileField() {
        var mode = $('#rex-form-ssl-ca-mode').val();
        $('[data-ssl-field="file"]').closest('.rex-form-group').toggle(mode === 'file');
    }

    $('#rex-form-ssl-ca-mode').on('change', toggleSslCaFileField);
    toggleSslCaFileField(); // Initial call
});
</script>
