<?php

$error = [];
$success = '';

$func = rex_request('func', 'string');

$csrfToken = rex_csrf_token::factory('system');

if (rex_request('rex_debug_updated', 'bool', false)) {
    $success = (rex::isDebugMode()) ? rex_i18n::msg('debug_mode_info_on') : rex_i18n::msg('debug_mode_info_off');
}

if ($func && !$csrfToken->isValid()) {
    $error[] = rex_i18n::msg('csrf_token_invalid');
} elseif ('setup' == $func && !rex::isLiveMode()) {
    // REACTIVATE SETUP
    if (false !== $url = rex_setup::startWithToken()) {
        header('Location:' . $url);
        exit;
    }
    $error[] = rex_i18n::msg('setup_error2');
} elseif ('generate' == $func) {
    // generate all articles,cats,templates,caches
    $success = rex_delete_cache();
} elseif ('updateassets' == $func && !rex::isLiveMode()) {
    rex_dir::copy(rex_path::core('assets'), rex_path::coreAssets());
    $success = 'Updated assets';
} elseif ('debugmode' == $func && !rex::isLiveMode()) {
    $configFile = rex_path::coreData('config.yml');
    $config = array_merge(
        rex_file::getConfig(rex_path::core('default.config.yml')),
        rex_file::getConfig($configFile),
    );

    if (!is_array($config['debug'])) {
        $config['debug'] = [];
    }

    $config['debug']['enabled'] = !rex::isDebugMode();
    rex::setProperty('debug', $config['debug']);
    if (rex_file::putConfig($configFile, $config)) {
        // reload the page so that debug mode is immediately visible
        rex_response::sendRedirect(rex_url::currentBackendPage(['rex_debug_updated' => true], false));
    }
} elseif ('updateinfos' == $func) {
    $configFile = rex_path::coreData('config.yml');
    $config = array_merge(
        rex_file::getConfig(rex_path::core('default.config.yml')),
        rex_file::getConfig($configFile),
    );

    $settings = rex_post('settings', 'array', []);

    foreach (['server', 'servername', 'error_email', 'lang'] as $key) {
        if (!isset($settings[$key]) || !$settings[$key]) {
            $error[] = rex_i18n::msg($key . '_required');
            continue;
        }
        $config[$key] = $settings[$key];
        try {
            rex::setProperty($key, $settings[$key]);
        } catch (InvalidArgumentException) {
            $error[] = rex_i18n::msg($key . '_invalid');
        }
    }

    foreach (rex_system_setting::getAll() as $setting) {
        $key = $setting->getKey();
        if (isset($settings[$key])) {
            if (true !== ($msg = $setting->setValue($settings[$key]))) {
                $error[] = $msg;
            }
        }
    }

    if (empty($error)) {
        if (rex_file::putConfig($configFile, $config)) {
            $success = rex_i18n::msg('info_updated');
        }
    }
} elseif ('update_editor' === $func) {
    $editor = rex_post('editor', [
        ['name', 'string', null],
        ['basepath', 'string', null],
        ['update_cookie', 'bool', false],
        ['delete_cookie', 'bool', false],
    ]);

    $editor['name'] = $editor['name'] ?: null;
    $editor['basepath'] = $editor['basepath'] ?: null;

    $cookieOptions = ['samesite' => 'strict'];

    if ($editor['delete_cookie']) {
        rex_response::clearCookie('editor', $cookieOptions);
        rex_response::clearCookie('editor_basepath', $cookieOptions);
        unset($_COOKIE['editor']);
        unset($_COOKIE['editor_basepath']);

        $success = rex_i18n::msg('system_editor_success_cookie_deleted');
    } elseif ($editor['update_cookie']) {
        rex_response::sendCookie('editor', $editor['name'], $cookieOptions);
        rex_response::sendCookie('editor_basepath', $editor['basepath'], $cookieOptions);
        $_COOKIE['editor'] = $editor['name'];
        $_COOKIE['editor_basepath'] = $editor['basepath'];

        $success = rex_i18n::msg('system_editor_success_cookie');
    } else {
        $configFile = rex_path::coreData('config.yml');
        $config = rex_file::getConfig($configFile);

        $config['editor'] = $editor['name'];
        $config['editor_basepath'] = $editor['basepath'];
        rex::setProperty('editor', $config['editor']);
        rex::setProperty('editor_basepath', $config['editor_basepath']);

        rex_file::putConfig($configFile, $config);
        $success = rex_i18n::msg('system_editor_success_configyml');
    }
}

$selLang = new rex_select();
$selLang->setStyle('class="form-control"');
$selLang->setName('settings[lang]');
$selLang->setId('rex-id-lang');
$selLang->setAttribute('class', 'form-control selectpicker');
$selLang->setSize(1);
$selLang->setSelected(rex::getProperty('lang'));
$locales = rex_i18n::getLocales();
asort($locales);
foreach ($locales as $locale) {
    $selLang->addOption(rex_i18n::msgInLocale('lang', $locale) . ' (' . $locale . ')', $locale);
}

if (!empty($error)) {
    echo rex_view::error(implode('<br />', $error));
}

if ('' != $success) {
    echo rex_view::success($success);
}

$dbconfig = rex::getDbConfig(1);

$rexVersion = rex::getVersion();
if (str_contains($rexVersion, '-dev')) {
    $hash = rex_version::gitHash(rex_path::base(), 'redaxo/core');
    if ($hash) {
        $rexVersion .= '#' . $hash;
    }
}

if (rex_version::isUnstable($rexVersion)) {
    $rexVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . rex_i18n::msg('unstable_version') . '"></i> ' . rex_escape($rexVersion);
}

$mainContent = [];
$sideContent = [];
$debugConfirm = '';

if (!rex::isDebugMode()) {
    $debugConfirm = ' data-confirm="' . rex_i18n::msg('debug_confirm') . '" ';
}

$content = '
    <h3>' . rex_i18n::msg('delete_cache') . '</h3>
    <p>' . rex_i18n::msg('delete_cache_description') . '</p>
    <p><a class="btn btn-delete" href="' . rex_url::currentBackendPage(['func' => 'generate'] + $csrfToken->getUrlParams()) . '">' . rex_i18n::msg('delete_cache') . '</a></p>';

if (!rex::isLiveMode()) {
    $content .= '
        <h3>' . rex_i18n::msg('debug_mode') . '</h3>
        <p>' . rex_i18n::msg('debug_mode_note') . '</p>
        <p><a class="btn btn-debug-mode" href="' . rex_url::currentBackendPage(['func' => 'debugmode'] + $csrfToken->getUrlParams()) . '" data-pjax="false"' . $debugConfirm . '><i class="rex-icon rex-icon-heartbeat"></i> ' . (rex::isDebugMode() ? rex_i18n::msg('debug_mode_off') : rex_i18n::msg('debug_mode_on')) . '</a></p>

        <h3>' . rex_i18n::msg('safemode') . '</h3>
        <p>' . rex_i18n::msg('safemode_text') . '</p>';

    $safemodeUrl = rex_url::currentBackendPage(['safemode' => '1'] + $csrfToken->getUrlParams());
    if (rex::isSafeMode()) {
        $safemodeUrl = rex_url::currentBackendPage(['safemode' => '0'] + $csrfToken->getUrlParams());
    }

    $content .= '
        <p><a class="btn btn-safemode-activate" href="' . $safemodeUrl . '" data-pjax="false">' . (rex::isSafeMode() ? rex_i18n::msg('safemode_deactivate') : rex_i18n::msg('safemode_activate')) . '</a></p>


        <h3>' . rex_i18n::msg('setup') . '</h3>
        <p>' . rex_i18n::msg('setup_text') . '</p>
        <p><a class="btn btn-setup" href="' . rex_url::currentBackendPage(['func' => 'setup'] + $csrfToken->getUrlParams()) . '" data-confirm="' . rex_i18n::msg('setup_restart') . '?" data-pjax="false">' . rex_i18n::msg('setup') . '</a></p>';
}
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('system_features'));
$fragment->setVar('body', $content, false);
$sideContent[] = $fragment->parse('core/page/section.php');

$content = '
    <table class="table">
        <tr>
            <th class="rex-table-width-3">REDAXO</th>
            <td>' . $rexVersion . '</td>
        </tr>
        <tr>
            <th>PHP</th>
            <td>' . PHP_VERSION . ' <a class="rex-link-expanded" href="' . rex_url::backendPage('system/phpinfo') . '" title="phpinfo" onclick="newWindow(\'phpinfo\', this.href, 1000,800,\',status=yes,resizable=yes\');return false;"><i class="rex-icon rex-icon-phpinfo"></i></a></td>
        </tr>
        <tr>
            <th>' . rex_i18n::msg('path') . '</th>
			<td>
			<div class="rex-word-break">' . rex_path::base() . '</div>
			</td>
        </tr>
    </table>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('installation'));
$fragment->setVar('content', $content, false);
$sideContent[] = $fragment->parse('core/page/section.php');

$sql = rex_sql::factory();

$content = '
    <table class="table">
        <tr>
            <th class="rex-table-width-3">' . rex_i18n::msg('version') . '</th>
            <td>' . $sql->getDbType() . ' ' . $sql->getDbVersion() . '</td>
        </tr>
        <tr>
            <th>' . rex_i18n::msg('name') . '</th>
            <td><span class="rex-word-break">' . $dbconfig->name . '</span></td>
        </tr>
        <tr>
            <th>' . rex_i18n::msg('host') . '</th>
            <td>' . $dbconfig->host . '</td>
        </tr>
    </table>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('database'));
$fragment->setVar('content', $content, false);
$sideContent[] = $fragment->parse('core/page/section.php');

$content = '';

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-id-server" class="required">' . rex_i18n::msg('server') . '</label>';
$n['field'] = '<input class="form-control" type="url" id="rex-id-server" name="settings[server]" value="' . rex_escape(rex::getServer()) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-servername" class="required">' . rex_i18n::msg('servername') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-id-servername" name="settings[servername]" value="' . rex_escape(rex::getServerName()) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang" class="required">' . rex_i18n::msg('backend_language') . '</label>';
$n['field'] = $selLang->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-error-email" class="required">' . rex_i18n::msg('error_email') . '</label>';
$n['field'] = '<input class="form-control" type="email" id="rex-id-error-email" name="settings[error_email]" value="' . rex_escape(rex::getErrorEmail()) . '" required />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

foreach (rex_system_setting::getAll() as $setting) {
    $field = $setting->getField();
    if (!$field instanceof rex_form_element) {
        throw new rex_exception($setting::class . '::getField() must return a rex_form_element!');
    }
    $field->setAttribute('name', 'settings[' . $setting->getKey() . ']');
    $content .= $field->get();
}

$formElements = [];

$editor = rex_editor::factory();
$configYml = rex_path::coreData('config.yml');
if ($url = $editor->getUrl($configYml, 0)) {
    $n = [];
    $n['label'] = '';
    $n['field'] = $n['field'] = '<a class="btn btn-sm btn-primary" href="' . $url . '">' . rex_i18n::msg('system_editor_open_file', rex_path::basename($configYml)) . '</a>';
    $n['note'] = rex_i18n::msg('system_edit_config_note');
    $formElements[] = $n;
}

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="sendit"' . rex::getAccesskey(rex_i18n::msg('system_update'), 'save') . '>' . rex_i18n::msg('system_update') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('system_settings'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$mainContent[] = '
<form id="rex-form-system-setup" action="' . rex_url::currentBackendPage() . '" method="post">
    <input type="hidden" name="func" value="updateinfos" />
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

$content = '<p>' . rex_i18n::msg('system_editor_note') . '</p>';

$viaCookie = array_key_exists('editor', $_COOKIE);
if ($viaCookie) {
    $content .= rex_view::info(rex_i18n::msg('system_editor_note_cookie'));
}

$formElements = [];

$selEditor = new rex_select();
$selEditor->setStyle('class="form-control"');
$selEditor->setName('editor[name]');
$selEditor->setId('rex-id-editor');
$selEditor->setAttribute('class', 'form-control selectpicker');
$selEditor->setSize(1);
$selEditor->setSelected($editor->getName());
$selEditor->addArrayOptions(['' => rex_i18n::msg('system_editor_no_editor')] + $editor->getSupportedEditors());

$n = [];
$n['label'] = '<label for="rex-id-editor">' . rex_i18n::msg('system_editor_name') . '</label>';
$n['field'] = $selEditor->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-editor-basepath">' . rex_i18n::msg('system_editor_basepath') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-id-editor-basepath" name="editor[basepath]" value="' . rex_escape($editor->getBasepath()) . '" />';
$n['note'] = rex_i18n::msg('system_editor_basepath_note');
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$class = 'rex-form-aligned';

if (!$viaCookie) {
    $n = [];
    $n['field'] = '<button class="btn btn-save ' . $class . '" type="submit" name="editor[update_cookie]" value="0">' . rex_i18n::msg('system_editor_update_configyml') . '</button>';
    $formElements[] = $n;
    $class = '';
}

$n = [];
$n['field'] = '<button class="btn btn-save ' . $class . '" type="submit" name="editor[update_cookie]" value="1">' . rex_i18n::msg('system_editor_update_cookie') . '</button>';
$formElements[] = $n;

if ($viaCookie) {
    $n = [];
    $n['field'] = '<button class="btn btn-delete" type="submit" name="editor[delete_cookie]" value="1">' . rex_i18n::msg('system_editor_delete_cookie') . '</button>';
    $formElements[] = $n;
}

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('system_editor'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$mainContent[] = '
<form id="rex-form-system-setup" action="' . rex_url::currentBackendPage() . '" method="post">
    <input type="hidden" name="func" value="update_editor" />
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

$fragment = new rex_fragment();
$fragment->setVar('content', [implode('', $mainContent), implode('', $sideContent)], false);
$fragment->setVar('classes', ['col-lg-8', 'col-lg-4'], false);
echo $fragment->parse('core/page/grid.php');
