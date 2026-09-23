<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Cache;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\ConnectionConfig;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\ArticleField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$error = [];
$success = '';

$func = Request::request('func', 'string');

$csrfToken = CsrfToken::factory('system');

if ($func && !$csrfToken->isValid()) {
    $error[] = I18n::msg('csrf_token_invalid');
} elseif ('generate' == $func) {
    // generate all articles,cats,templates,caches
    $success = Cache::delete();
} elseif ('updateassets' == $func && !Core::isHardenedMode()) {
    Dir::copy(Path::core('assets'), Path::coreAssets());

    $success = 'Updated assets';
} elseif ('updateinfos' == $func) {
    $settings = Request::post('settings', 'array', []);

    foreach ($settings as $key => $value) {
        switch ($key) {
            case 'lang':
                // the select offers only existing locales, anything else is a manipulated request
                if (!in_array($value, I18n::getLocales(), true)) {
                    throw new InvalidArgumentException('Invalid language "' . Type::string($value) . '".');
                }
                Core::setConfig('lang', $value);
                break;

            case 'start_article_id':
            case 'notfound_article_id':
                $value = (int) $value;
                $article = Article::get($value);
                if (!$article instanceof Article) {
                    $error[] = I18n::msg('system_setting_' . $key . '_invalid');
                }
                Core::setConfig($key, $value);
                break;

            case 'article_history':
            case 'article_work_version':
                $value = (bool) $value;
                Core::setConfig($key, $value);
                break;
        }
    }

    if (empty($error)) {
        $success = I18n::msg('info_updated');
    }
} elseif ('update_editor' === $func) {
    $editor = Request::post('editor', [
        ['name', 'string', null],
        ['basepath', 'string', null],
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
    } else {
        Response::sendCookie('editor', $editor['name'], $cookieOptions);
        Response::sendCookie('editor_basepath', $editor['basepath'], $cookieOptions);
        $_COOKIE['editor'] = $editor['name'];
        $_COOKIE['editor_basepath'] = $editor['basepath'];

        $success = I18n::msg('system_editor_success_cookie');
    }
}

$selLang = new Select();
$selLang->setStyle('class="form-control"');
$selLang->setName('settings[lang]');
$selLang->setId('rex-id-lang');
$selLang->setAttribute('class', 'form-control selectpicker');
$selLang->setSize(1);
$selLang->setSelected(I18n::$defaultLocale);
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

$dbconfig = ConnectionConfig::get(1);

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

$content = '
    <h3>' . I18n::msg('delete_cache') . '</h3>
    <p>' . I18n::msg('delete_cache_description') . '</p>
    <p><a class="btn btn-delete" href="' . Url::currentBackendPage(['func' => 'generate'] + $csrfToken->getUrlParams()) . '">' . I18n::msg('delete_cache') . '</a></p>';

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
            <th>' . I18n::msg('mode') . '</th>
            <td>' . Core::getMode()->value . '</td>
        </tr>
        <tr>
            <th>PHP</th>
            <td>' . PHP_VERSION . ' <a class="rex-link-expanded" href="' . Url::backendPage('system/phpinfo') . '" title="phpinfo" onclick="newWindow(\'phpinfo\', this.href, 1000,800,\',status=yes,resizable=yes\');return false;"><i class="rex-icon rex-icon-phpinfo"></i></a></td>
        </tr>
        <tr>
            <th>' . I18n::msg('base_url') . '</th>
            <td><span class="rex-word-break">' . escape(Core::getBaseUrl()) . '</span></td>
        </tr>
        <tr>
            <th>' . I18n::msg('error_email') . '</th>
            <td><span class="rex-word-break">' . escape(Env::get('REX_ERROR_EMAIL') ?? I18n::msg('deactivated')) . '</span></td>
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
$n['label'] = '<label for="rex-id-lang" class="required">' . I18n::msg('backend_language') . '</label>';
$n['field'] = $selLang->get();
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
$field->setAttribute('name', 'settings[article_history]');
$field->setLabel(I18n::msg('system_setting_article_history'));
$select = $field->getSelect();
$select->addOption(I18n::msg('activated'), 1);
$select->addOption(I18n::msg('deactivated'), 0);
$select->setSelected(Core::getConfig('article_history', false) ? 1 : 0);
$content .= $field->get();

$field = new SelectField();
$field->setAttribute('class', 'form-control selectpicker');
$field->setAttribute('name', 'settings[article_work_version]');
$field->setLabel(I18n::msg('system_setting_article_work_version'));
$select = $field->getSelect();
$select->addOption(I18n::msg('activated'), 1);
$select->addOption(I18n::msg('deactivated'), 0);
$select->setSelected(Core::getConfig('article_work_version', false) ? 1 : 0);
$content .= $field->get();

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="sendit"' . Accesskey::attributes(I18n::msg('system_update'), 'save') . '>' . I18n::msg('system_update') . '</button>';
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
} elseif (Env::get('REX_EDITOR')) {
    $content .= Message::info(I18n::msg('system_editor_note_env'));
}

$formElements = [];

$editor = Editor::factory();
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

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit">' . I18n::msg('system_editor_update_cookie') . '</button>';
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
