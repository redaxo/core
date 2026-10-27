<?php

use Redaxo\Core\Backend\Form\EditorSettings;
use Redaxo\Core\Backend\Form\SystemSettings;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\ConnectionConfig;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\ButtonStyle;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\Storage\ConfigStorage;
use Redaxo\Core\Form\SubmitButton;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;
use Symfony\Component\Mime\Address;

use function Redaxo\Core\View\escape;

$error = [];
$success = '';

$func = Request::request('func', 'string');

$csrfToken = CsrfToken::factory('system');

$mailer = null;
$mailerError = null;
if (null === Env::get('MAILER_DSN')) {
    $mailerError = I18n::msg('mailer_not_configured');
} else {
    try {
        $mailer = Core::getMailer();
    } catch (MailerException|InvalidArgumentException $exception) {
        $mailerError = $exception->getMessage();
    }
}
$systemSettings = new SystemSettings();
$configStorage = new ConfigStorage(Core::CONFIG_NAMESPACE);
$configStorage->load($systemSettings);

$systemForm = new Form($systemSettings, title: new Translatable('system_settings'), buttons: [SubmitButton::save(new Translatable('system_update'))]);

if ($systemForm->handleRequest()) {
    $configStorage->save($systemSettings);
    $success = I18n::msg('info_updated');
}

$editor = Editor::factory();
$editorSettings = new EditorSettings();
$editorSettings->name = $editor->getName();
$editorSettings->basepath = $editor->getBasepath();

$editorForm = new Form(
    $editorSettings,
    title: new Translatable('system_editor'),
    buttons: [
        SubmitButton::save(new Translatable('system_editor_update_cookie')),
        new SubmitButton('delete_cookie', new Translatable('system_editor_delete_cookie'), style: ButtonStyle::Danger),
    ],
);

$editorValid = $editorForm->handleRequest();
$cookieOptions = ['samesite' => 'strict'];

if ($editorForm->isSubmittedBy('delete_cookie')) {
    Response::clearCookie('editor', $cookieOptions);
    Response::clearCookie('editor_basepath', $cookieOptions);
    unset($_COOKIE['editor']);
    unset($_COOKIE['editor_basepath']);

    $success = I18n::msg('system_editor_success_cookie_deleted');
} elseif ($editorValid) {
    Response::sendCookie('editor', $editorSettings->name, $cookieOptions);
    Response::sendCookie('editor_basepath', $editorSettings->basepath, $cookieOptions);
    $_COOKIE['editor'] = $editorSettings->name;
    $_COOKIE['editor_basepath'] = $editorSettings->basepath;

    $success = I18n::msg('system_editor_success_cookie');
}

if ($func && !$csrfToken->isValid()) {
    $error[] = I18n::msg('csrf_token_invalid');
} elseif ('generate' == $func) {
    // generate all articles,cats,templates,caches
    $success = Cache::delete();
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
            <td>' . PHP_VERSION . ' <a class="rex-link-expanded" href="' . Url::backendPage('system/phpinfo') . '" title="phpinfo" target="_blank" rel="noopener"><i class="rex-icon rex-icon-phpinfo"></i></a></td>
        </tr>
        <tr>
            <th>' . I18n::msg('base_url') . '</th>
            <td><span class="rex-word-break">' . escape(Url::absoluteBase()) . '</span></td>
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

$addresses = static fn (array $addresses): string => escape(implode(', ', array_map(static fn (Address $address) => $address->toString(), $addresses)));

if ($mailer instanceof Mailer) {
    $content = '
        <table class="table">
            <tr>
                <th class="rex-table-width-3">' . I18n::msg('mailer_transport') . '</th>
                <td><span class="rex-word-break">' . escape((string) $mailer->transport) . '</span></td>
            </tr>
            <tr>
                <th>' . I18n::msg('mailer_from') . '</th>
                <td><span class="rex-word-break">' . (null === $mailer->defaultFrom ? '-' : $addresses([$mailer->defaultFrom])) . '</span></td>
            </tr>
            <tr>
                <th>' . I18n::msg('mailer_detour') . '</th>
                <td><span class="rex-word-break">' . ($mailer->detourRecipients ? '<span class="text-danger">' . $addresses($mailer->detourRecipients) . '</span>' : I18n::msg('deactivated')) . '</span></td>
            </tr>
            <tr>
                <th>' . I18n::msg('mailer_log') . '</th>
                <td>' . $mailer->logMode->value . '</td>
            </tr>
            <tr>
                <th>' . I18n::msg('mailer_archive') . '</th>
                <td>' . I18n::msg($mailer->archive ? 'activated' : 'deactivated') . '</td>
            </tr>
        </table>';

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('mailer'));
    $fragment->setVar('content', $content, false);
} else {
    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('mailer'));
    $fragment->setVar('body', Message::error(escape((string) $mailerError)), false);
}
$sideContent[] = $fragment->parse('core/page/section.php');

$mainContent[] = $systemForm->render();

$viaCookie = array_key_exists('editor', $_COOKIE);

$mainContent[] = $editorForm->capture(static function (Form $form) use ($viaCookie): void { ?>
    <section class="rex-section rex-section--edit">
        <header class="rex-section__header"><div class="rex-section__title"><?= Html::from($form->title ?? '') ?></div></header>
        <div class="rex-section__body">
            <p><?= I18n::msg('system_editor_note') ?></p>
            <?php if ($viaCookie): ?>
                <?= Message::info(I18n::msg('system_editor_note_cookie')) ?>
            <?php elseif (Env::get('REX_EDITOR')): ?>
                <?= Message::info(I18n::msg('system_editor_note_env')) ?>
            <?php endif ?>
            <?= $form->rest() ?>
        </div>
        <footer class="rex-section__footer">
            <div class="rex-form-panel-footer">
                <div class="btn-toolbar">
                    <?= $form->button('save', ['class' => ['rex-form-aligned']]) ?>
                    <?php if ($viaCookie): ?>
                        <?= $form->button('delete_cookie') ?>
                    <?php endif ?>
                </div>
            </div>
        </footer>
    </section>
<?php });

$fragment = new Fragment();
$fragment->setVar('content', [implode('', $mainContent), implode('', $sideContent)], false);
$fragment->setVar('classes', ['col-lg-8', 'col-lg-4'], false);
echo $fragment->parse('core/page/grid.php');
