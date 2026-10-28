<?php

use Redaxo\Core\Cronjob\Cronjob;
use Redaxo\Core\Cronjob\CronjobManager;
use Redaxo\Core\Cronjob\CronjobRegistry;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$error = '';
$success = '';
$message = '';
$logFile = Path::log('cronjob.log');

$csrfToken = CsrfToken::factory('cronjob_log');

if ('cronjob_delLog' == $func && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('cronjob_delLog' == $func) {
    if (LogFile::delete($logFile)) {
        $success = I18n::msg('syslog_deleted');
    } else {
        $error = I18n::msg('syslog_delete_error');
    }
}
if ('' != $success) {
    $message .= Message::success($success);
}
if ('' != $error) {
    $message .= Message::error($error);
}
$content = '';

$rows = [];
$file = LogFile::factory($logFile);

/** @var LogEntry $entry */
foreach (new LimitIterator($file, 0, 100) as $entry) {
    $data = $entry->getData();
    $trigger = Type::string($data[3] ?? '');
    $rows[] = [
        'error' => 'ERROR' == trim($data[0] ?? ''),
        'icon' => Html::raw('<i class="rex-icon rex-icon-cronjob" aria-hidden="true"></i>'),
        'date' => Formatter::intlDateTime($entry->getTimestamp(), [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]),
        'name' => Type::string($data[1] ?? ''),
        'message' => Html::raw(nl2br(escape(Type::string($data[2] ?? '')))),
        'trigger' => 'schedule' === $trigger || 'manual' === $trigger ? I18n::msg('cronjob_trigger_' . $trigger) : $trigger,
    ];
}

$content .= (string) new Table([
    TableColumn::forKey('', 'icon', ColumnType::Icon),
    TableColumn::forKey(I18n::rawMsg('cronjob_log_date'), 'date', attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
    TableColumn::forKey(I18n::rawMsg('cronjob_name'), 'name'),
    TableColumn::forKey(I18n::rawMsg('cronjob_log_message'), 'message'),
    TableColumn::forKey(I18n::rawMsg('cronjob_trigger'), 'trigger'),
], $rows, rowAttributes: static fn (array $row): HtmlAttributes => new HtmlAttributes([
    'class' => $row['error'] ? 'rex-state-error' : 'rex-state-success',
]), attributes: new HtmlAttributes(['data-hover' => true]))->render();

$formElements = [];

// XXX calc last line and use it instead
if ($url = Editor::factory()->getUrl($logFile, 1)) {
    $n = [];
    $n['field'] = '<a class="rex-button" data-variant="success" href="' . $url . '">' . I18n::msg('system_editor_open_file', Path::basename($logFile)) . '</a>';
    $formElements[] = $n;
}

$n = [];
$n['field'] = '<button class="rex-button" data-variant="danger" type="submit" name="del_btn" data-confirm="' . I18n::msg('cronjob_delete_log_msg') . '?">' . I18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('cronjob_log'), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        <input type="hidden" name="func" value="cronjob_delLog" />
        ' . $content . '
    </form>';

echo $message;

try {
    $setting = CronjobManager::getSetting();
} catch (InvalidArgumentException $exception) {
    echo Message::error(escape($exception->getMessage()));
    $setting = null;
}

if (['all' => false, 'names' => []] === $setting) {
    echo Message::warning(I18n::msg('cronjob_disabled_by_env'));
}
if (null !== $setting && $unknown = CronjobManager::getUnknownNames()) {
    echo Message::warning(I18n::msg('cronjob_unknown_names_in_env', escape(implode(', ', $unknown))));
}

$cronjobs = CronjobRegistry::all();
$states = CronjobManager::getStates();
$now = new DateTimeImmutable();

$overview = '';
$overviewBody = '';

if (!$cronjobs) {
    $overviewBody = I18n::msg('cronjob_no_cronjobs');
} else {
    $overview = (string) new Table([
        new TableColumn('', static fn (): Html => Html::raw('<i class="rex-icon rex-icon-cronjob" aria-hidden="true"></i>'), ColumnType::Icon),
        new TableColumn(I18n::rawMsg('cronjob_name'), static fn (Cronjob $cronjob): Html => Html::raw(escape($cronjob->name) . '<br /><small class="rex-u-text-muted">' . escape($cronjob->class . '::' . $cronjob->method . '()') . '</small>')),
        new TableColumn(I18n::rawMsg('cronjob_schedule'), static fn (Cronjob $cronjob): Html => Html::raw('<code class="rex-u-nowrap">' . escape($cronjob->schedule) . '</code>')),
        new TableColumn(I18n::rawMsg('cronjob_lastrun'), static function (Cronjob $cronjob) use ($states): Html {
            $state = $states[$cronjob->name] ?? null;
            $lastRun = $state['lastRun'] ?? null;
            if (null === $state || null === $lastRun) {
                return Html::raw('–');
            }

            $text = Formatter::intlDateTime($lastRun);
            if ($state['running']) {
                return Html::raw($text . ' <span class="rex-u-text-muted">(' . I18n::msg('cronjob_running') . ')</span>');
            }
            if (true === $state['lastSuccess']) {
                return Html::raw('<span class="rex-u-online"><i class="rex-icon rex-icon-active-true" aria-hidden="true"></i> ' . $text . '</span>');
            }
            if (false === $state['lastSuccess']) {
                return Html::raw('<span class="rex-u-offline"><i class="rex-icon rex-icon-active-false" aria-hidden="true"></i> ' . $text . '</span>');
            }
            return Html::raw($text);
        }, attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
        new TableColumn(I18n::rawMsg('cronjob_nexttime'), static function (Cronjob $cronjob) use ($states, $setting, $now): Html|string {
            if (null === $setting) {
                return '–';
            }
            if (!CronjobManager::isEnabled($cronjob->name)) {
                return Html::raw('<span class="rex-u-text-muted">' . I18n::msg('cronjob_not_enabled') . '</span>');
            }
            return Formatter::intlDateTime($cronjob->getNextRunDate($states[$cronjob->name]['lastRun'] ?? $now));
        }, attributes: new HtmlAttributes(['class' => ['rex-table-tabular-nums']])),
    ], $cronjobs, attributes: new HtmlAttributes(['data-hover' => true]))->render();
}

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('cronjob_caption'), false);
$fragment->setVar('body', $overviewBody, false);
$fragment->setVar('content', $overview, false);
$fragment->setVar('footer', I18n::rawMsg('cronjob_notice', escape(Path::bin('console') . ' cronjob:run')), false);
echo $fragment->parse('core/page/section.php');

echo $content;
