<?php

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Editor;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;

if (!isset($logFile)) {
    $logFile = ini_get('error_log');
}

$rows = [];

$buttons = '';
if (!is_file($logFile) || !is_readable($logFile) || filesize($logFile) <= 0) {
    $rows[] = I18n::msg('syslog_empty');
} else {
    // TODO make this more effienct with things like LogFile->next()
    $file = new SplFileObject($logFile, 'r');
    $file->seek(PHP_INT_MAX);
    $lastLine = $file->key();

    $limit = 30;
    try {
        $lines = iterator_to_array(new LimitIterator($file, max(0, $lastLine - $limit), $lastLine));
    } catch (OutOfBoundsException) {
        // handle logfiles which contain a single line of text, no newlines.
        // "Cannot seek to 0 which is behind offset 0 plus count 0"
        $lines = file($logFile);
    }
    foreach (array_reverse($lines) as $logLine) {
        if (empty(trim($logLine))) {
            continue;
        }

        $rows[] = Type::string($logLine);
    }

    if ($url = Editor::factory()->getUrl($logFile, $lastLine)) {
        $formElements = [];

        $n = [];
        $n['field'] = '<a class="rex-button" data-variant="success" href="' . $url . '">' . I18n::msg('system_editor_open_file', Path::basename($logFile)) . '</a>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
    }
}

$content = (string) new Table([
    new TableColumn('', static fn (string $line): string => $line),
], $rows, attributes: new HtmlAttributes(['data-hover' => true]))->render();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('syslog_title', $logFile), false);
$fragment->setVar('content', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
