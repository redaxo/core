<?php

use Redaxo\Core\Cache;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\ArticleField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$error = [];
$success = '';

$func = Request::request('func', 'string');

$csrfToken = CsrfToken::factory('system');

if (Request::request('rex_debug_updated', 'bool', false)) {
    $success = (Core::isDebugMode()) ? I18n::msg('debug_mode_info_on') : I18n::msg('debug_mode_info_off');
}

if ($func && !$csrfToken->isValid()) {
    $error[] = I18n::msg('csrf_token_invalid');
} elseif ('setup' == $func && !Core::isLiveMode()) {
    // REACTIVATE SETUP
    if (false !== $url = Setup::startWithToken()) {
        header('Location:' . $url);
        exit;
    }
    $error[] = I18n::msg('setup_error2');
} elseif ('generate' == $func) {
    // generate all articles,cats,templates,caches
    $success = Cache::delete();
} elseif ('updateassets' == $func && !Core::isLiveMode()) {
    Dir::copy(Path::core('assets'), Path::coreAssets());

    $files = require Path::core('assets_src/vendor_files.php');
    foreach ($files as $source => $destination) {
        File::copy(Path::core('assets_src/' . $source), Path::coreAssets($destination));
    }

    $success = 'Updated assets';
} elseif ('debugmode' == $func && !Core::isLiveMode()) {
    $configFile = Path::coreData('config.yml');
    $config = array_merge(
        File::getConfig(Path::core('setup/default.config.yml')),
        File::getConfig($configFile),
    );

    if (!is_array($config['debug'])) {
        $config['debug'] = [];
    }

    $config['debug']['enabled'] = !Core::isDebugMode();
    Core::setProperty('debug', $config['debug']);
    if (File::putConfig($configFile, $config)) {
        // reload the page so that debug mode is immediately visible
        Response::sendRedirect(Url::currentBackendPage(['rex_debug_updated' => true]));
    }
} elseif ('updateinfos' == $func) {
    $configFile = Path::coreData('config.yml');
    $config = array_merge(
        File::getConfig(Path::core('setup/default.config.yml')),
        File::getConfig($configFile),
    );

    $settings = Request::post('settings', 'array', []);

    foreach (['server', 'servername', 'error_email', 'lang'] as $key) {
        if (!isset($settings[$key]) || !$settings[$key]) {
            $error[] = I18n::msg($key . '_required');
            continue;
        }
        $config[$key] = $settings[$key];
        try {
            Core::setProperty($key, $settings[$key]);
        } catch (InvalidArgumentException) {
            $error[] = I18n::msg($key . '_invalid');
        }
    }

    foreach ($settings as $key => $value) {
        switch ($key) {
            case 'start_article_id':
            case 'notfound_article_id':
                $value = (int) $value;
                $article = Article::get($value);
                if (!$article instanceof Article) {
                    $error[] = I18n::msg('system_setting_' . $key . '_invalid');
                }
                Core::setConfig($key, $value);
                break;

            case 'default_template_id':
                $value = (int) $value;
                $sql = Sql::factory();
                $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'template WHERE id=? AND active=1', [$value]);
                if (1 != $sql->getRows() && 0 != $value) {
                    $error[] = I18n::msg('system_setting_default_template_id_invalid');
                }
                Core::setConfig('default_template_id', $value);
                break;

            case 'article_history':
            case 'article_work_version':
                $value = (bool) $value;
                Core::setConfig($key, $value);
                break;

            case 'phpmailer_errormail':
                $value = (int) $value;
                Core::setConfig('phpmailer_errormail', $value);
                break;
        }
    }

    if (empty($error)) {
        if (File::putConfig($configFile, $config)) {
            $success = I18n::msg('info_updated');
        }
    }
} elseif ('update_editor' === $func) {
    $editor = Request::post('editor', [
        ['name', 'string', null],
        ['basepath', 'string', null],
        ['update_cookie', 'bool', false],
        ['delete_cookie', 'bool', false],
    ]);

    $editor['name'] = $editor['name'] ?: null;
    $editor['basepath'] = $editor['basepath'] ?: null;

    $cookieOptions = ['samesite' => 'strict'];

    if ($editor['delete_cookie']) {
        Response::clearCookie('editor', $cookieOptions);
        Response::clearCookie('editor_basepath', $cookieOptions);
        unset($_COOKIE['editor']);
        unset($_COOKIE['editor_basepath']);

        $success = I18n::msg('system_editor_success_cookie_deleted');
    } elseif ($editor['update_cookie']) {
        Response::sendCookie('editor', $editor['name'], $cookieOptions);
        Response::sendCookie('editor_basepath', $editor['basepath'], $cookieOptions);
        $_COOKIE['editor'] = $editor['name'];
        $_COOKIE['editor_basepath'] = $editor['basepath'];

        $success = I18n::msg('system_editor_success_cookie');
    } else {
        $configFile = Path::coreData('config.yml');
        $config = File::getConfig($configFile);

        $config['editor'] = $editor['name'];
        $config['editor_basepath'] = $editor['basepath'];
        Core::setProperty('editor', $config['editor']);
        Core::setProperty('editor_basepath', $config['editor_basepath']);

        File::putConfig($configFile, $config);
        $success = I18n::msg('system_editor_success_configyml');
    }
}

$selLang = new Select();
$selLang->setStyle('class="form-control"');
$selLang->setName('settings[lang]');
$selLang->setId('rex-id-lang');
$selLang->setAttribute('class', 'form-control selectpicker');
$selLang->setSize(1);
$selLang->setSelected(Core::getProperty('lang'));
$locales = I18n::getLocales();
asort($locales);
foreach ($locales as $locale) {
    $selLang->addOption(I18n::msgInLocale('lang', $locale) . ' (' . $locale . ')', $locale);
}

if (!empty($error)) {
    echo Message::error(implode('<br />', $error));
}

if ('' != $success) {
    echo Message::success($success);
}

$dbconfig = Core::getDbConfig(1);

$rexVersion = Core::getVersion();
if (str_contains($rexVersion, '-dev')) {
    $hash = Version::gitHash(Path::base(), 'redaxo/core');
    if ($hash) {
        $rexVersion .= '#' . $hash;
    }
}

if (Version::isUnstable($rexVersion)) {
    $rexVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . escape($rexVersion);
}

$mainContent = [];
$sideContent = [];
$debugConfirm = '';

if (!Core::isDebugMode()) {
    $debugConfirm = ' data-confirm="' . I18n::msg('debug_confirm') . '" ';
}

$content = '
    <h3>' . I18n::msg('delete_cache') . '</h3>
    <p>' . I18n::msg('delete_cache_description') . '</p>
    <p><a class="btn btn-delete" href="' . Url::currentBackendPage(['func' => 'generate'] + $csrfToken->getUrlParams()) . '">' . I18n::msg('delete_cache') . '</a></p>';

if (!Core::isLiveMode()) {
    $content .= '
        <h3>' . I18n::msg('debug_mode') . '</h3>
        <p>' . I18n::msg('debug_mode_note') . '</p>
        <p><a class="btn btn-debug-mode" href="' . Url::currentBackendPage(['func' => 'debugmode'] + $csrfToken->getUrlParams()) . '" data-pjax="false"' . $debugConfirm . '><i class="rex-icon rex-icon-heartbeat"></i> ' . (Core::isDebugMode() ? I18n::msg('debug_mode_off') : I18n::msg('debug_mode_on')) . '</a></p>

        <h3>' . I18n::msg('safemode') . '</h3>
        <p>' . I18n::msg('safemode_text') . '</p>';

    $safemodeUrl = Url::currentBackendPage(['safemode' => '1'] + $csrfToken->getUrlParams());
    if (Core::isSafeMode()) {
        $safemodeUrl = Url::currentBackendPage(['safemode' => '0'] + $csrfToken->getUrlParams());
    }

    $content .= '
        <p><a class="btn btn-safemode-activate" href="' . $safemodeUrl . '" data-pjax="false">' . (Core::isSafeMode() ? I18n::msg('safemode_deactivate') : I18n::msg('safemode_activate')) . '</a></p>


        <h3>' . I18n::msg('setup') . '</h3>
        <p>' . I18n::msg('setup_text') . '</p>
        <p><a class="btn btn-setup" href="' . Url::currentBackendPage(['func' => 'setup'] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('setup_restart') . '?" data-pjax="false">' . I18n::msg('setup') . '</a></p>';
}
$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('system_features'));
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
            <td>' . PHP_VERSION . ' <a class="rex-link-expanded" href="' . Url::backendPage('system/phpinfo') . '" title="phpinfo" onclick="newWindow(\'phpinfo\', this.href, 1000,800,\',status=yes,resizable=yes\');return false;"><i class="rex-icon rex-icon-phpinfo"></i></a></td>
        </tr>
        <tr>
            <th>' . I18n::msg('path') . '</th>
			<td>
			<div class="rex-word-break">' . Path::base() . '</div>
			</td>
        </tr>
    </table>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('installation'));
$fragment->setVar('content', $content, false);
$sideContent[] = $fragment->parse('core/page/section.php');

$sql = Sql::factory();

$content = '
    <table class="table">
        <tr>
            <th class="rex-table-width-3">' . I18n::msg('version') . '</th>
            <td>' . $sql->getDbType() . ' ' . $sql->getDbVersion() . '</td>
        </tr>
        <tr>
            <th>' . I18n::msg('name') . '</th>
            <td><span class="rex-word-break">' . $dbconfig->name . '</span></td>
        </tr>
        <tr>
            <th>' . I18n::msg('host') . '</th>
            <td>' . $dbconfig->host . '</td>
        </tr>
    </table>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('database'));
$fragment->setVar('content', $content, false);
$sideContent[] = $fragment->parse('core/page/section.php');

$content = '';

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-id-server" class="required">' . I18n::msg('server') . '</label>';
$n['field'] = '<input class="form-control" type="url" id="rex-id-server" name="settings[server]" value="' . escape(Core::getServer()) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-servername" class="required">' . I18n::msg('servername') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-id-servername" name="settings[servername]" value="' . escape(Core::getServerName()) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang" class="required">' . I18n::msg('backend_language') . '</label>';
$n['field'] = $selLang->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-error-email" class="required">' . I18n::msg('error_email') . '</label>';
$n['field'] = '<input class="form-control" type="email" id="rex-id-error-email" name="settings[error_email]" value="' . escape(Core::getErrorEmail()) . '" required />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$field = new ArticleField();
$field->setAttribute('class', 'rex-form-widget');
$field->setAttribute('name', 'settings[start_article_id]');
$field->setLabel(I18n::msg('system_setting_start_article_id'));
$field->setValue(Core::getConfig('start_article_id', 1));
$content .= $field->get();

$field = new ArticleField();
$field->setAttribute('class', 'rex-form-widget');
$field->setAttribute('name', 'settings[notfound_article_id]');
$field->setLabel(I18n::msg('system_setting_notfound_article_id'));
$field->setValue(Core::getConfig('notfound_article_id', 1));
$content .= $field->get();

$field = new SelectField();
$field->setAttribute('class', 'form-control selectpicker');
$field->setAttribute('name', 'settings[default_template_id]');
$field->setLabel(I18n::msg('system_setting_default_template_id'));
$select = $field->getSelect();
$select->setSize(1);
$select->setSelected(Template::getDefaultId());

$templates = Template::getTemplatesForCategory(0);
if (empty($templates)) {
    $select->addOption(I18n::msg('option_no_template'), 0);
} else {
    $select->addArrayOptions(array_map(I18n::translate(...), $templates));
}
$content .= $field->get();

$field = new SelectField();
$field->setAttribute('class', 'form-control selectpicker');
$field->setAttribute('name', 'settings[article_history]');
$field->setLabel(I18n::msg('system_setting_article_history'));
$select = $field->getSelect();
$select->addOption(I18n::msg('package_active'), 1);
$select->addOption(I18n::msg('package_disabled'), 0);
$select->setSelected(Core::getConfig('article_history', false) ? 1 : 0);
$content .= $field->get();

$field = new SelectField();
$field->setAttribute('class', 'form-control selectpicker');
$field->setAttribute('name', 'settings[article_work_version]');
$field->setLabel(I18n::msg('system_setting_article_work_version'));
$select = $field->getSelect();
$select->addOption(I18n::msg('package_active'), 1);
$select->addOption(I18n::msg('package_disabled'), 0);
$select->setSelected(Core::getConfig('article_work_version', false) ? 1 : 0);
$content .= $field->get();

$field = new SelectField();
$field->setAttribute('class', 'form-control selectpicker');
$field->setAttribute('name', 'settings[phpmailer_errormail]');
$field->setLabel(I18n::msg('system_setting_errormail'));
$select = $field->getSelect();
$select->addOption(I18n::msg('phpmailer_errormail_disabled'), 0);
$select->addOption(I18n::msg('phpmailer_errormail_15min'), 900);
$select->addOption(I18n::msg('phpmailer_errormail_30min'), 1800);
$select->addOption(I18n::msg('phpmailer_errormail_60min'), 3600);
$select->setSelected(Core::getConfig('phpmailer_errormail', 1));
$content .= $field->get();

$formElements = [];

$editor = Editor::factory();
$configYml = Path::coreData('config.yml');
if ($url = $editor->getUrl($configYml, 0)) {
    $n = [];
    $n['label'] = '';
    $n['field'] = $n['field'] = '<a class="btn btn-sm btn-primary" href="' . $url . '">' . I18n::msg('system_editor_open_file', Path::basename($configYml)) . '</a>';
    $n['note'] = I18n::msg('system_edit_config_note');
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="sendit"' . Core::getAccesskey(I18n::msg('system_update'), 'save') . '>' . I18n::msg('system_update') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('system_settings'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$mainContent[] = '
<form id="rex-form-system-setup" action="' . Url::currentBackendPage() . '" method="post">
    <input type="hidden" name="func" value="updateinfos" />
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

$content = '<p>' . I18n::msg('system_editor_note') . '</p>';

$viaCookie = array_key_exists('editor', $_COOKIE);
if ($viaCookie) {
    $content .= Message::info(I18n::msg('system_editor_note_cookie'));
}

$formElements = [];

$selEditor = new Select();
$selEditor->setStyle('class="form-control"');
$selEditor->setName('editor[name]');
$selEditor->setId('rex-id-editor');
$selEditor->setAttribute('class', 'form-control selectpicker');
$selEditor->setSize(1);
$selEditor->setSelected($editor->getName());
$selEditor->addArrayOptions(['' => I18n::msg('system_editor_no_editor')] + $editor->getSupportedEditors());

$n = [];
$n['label'] = '<label for="rex-id-editor">' . I18n::msg('system_editor_name') . '</label>';
$n['field'] = $selEditor->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-editor-basepath">' . I18n::msg('system_editor_basepath') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-id-editor-basepath" name="editor[basepath]" value="' . escape($editor->getBasepath()) . '" />';
$n['note'] = I18n::msg('system_editor_basepath_note');
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$class = 'rex-form-aligned';

if (!$viaCookie) {
    $n = [];
    $n['field'] = '<button class="btn btn-save ' . $class . '" type="submit" name="editor[update_cookie]" value="0">' . I18n::msg('system_editor_update_configyml') . '</button>';
    $formElements[] = $n;
    $class = '';
}

$n = [];
$n['field'] = '<button class="btn btn-save ' . $class . '" type="submit" name="editor[update_cookie]" value="1">' . I18n::msg('system_editor_update_cookie') . '</button>';
$formElements[] = $n;

if ($viaCookie) {
    $n = [];
    $n['field'] = '<button class="btn btn-delete" type="submit" name="editor[delete_cookie]" value="1">' . I18n::msg('system_editor_delete_cookie') . '</button>';
    $formElements[] = $n;
}

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('system_editor'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$mainContent[] = '
<form id="rex-form-system-setup" action="' . Url::currentBackendPage() . '" method="post">
    <input type="hidden" name="func" value="update_editor" />
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

$fragment = new Fragment();
$fragment->setVar('content', [implode('', $mainContent), implode('', $sideContent)], false);
$fragment->setVar('classes', ['col-lg-8', 'col-lg-4'], false);
echo $fragment->parse('core/page/grid.php');
