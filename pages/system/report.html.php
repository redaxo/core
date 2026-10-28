<?php

use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\SystemReport;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

$report = SystemReport::factory()->get();

echo '<div class="rex-grid"><div data-span="sm:6">';

foreach ($report as $title => $group) {
    if (SystemReport::TITLE_PACKAGES === $title) {
        echo '</div><div data-span="sm:6">';
    }

    $rows = [];
    foreach ($group as $label => $value) {
        if (SystemReport::TITLE_PACKAGES === $title || SystemReport::TITLE_REDAXO === $title) {
            if (null === $value) {
                throw new RuntimeException('Package ' . $label . ' does not define a proper version in its composer.json.');
            }
            if (Version::isUnstable($value)) {
                $value = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '" role="img"></i> ' . escape($value);
            }
        } elseif (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        } else {
            $value = escape($value);
        }

        $rows[] = [(string) $label, new TableCell(Html::raw((string) $value), attributes: new HtmlAttributes(['data-title' => (string) $label]))];
    }

    $content = (string) new Table([
        TableColumn::forKey('', 0, attributes: new HtmlAttributes(['width' => '120']), rowHeader: true),
        TableColumn::forKey('', 1),
    ], $rows, attributes: new HtmlAttributes(['data-hover' => true, 'data-bordered' => true]))->render();

    $fragment = new Fragment();
    $fragment->setVar('title', $title);

    if (SystemReport::TITLE_PHP === $title) {
        $phpinfo = '<a href="' . Url::backendPage('system/phpinfo') . '" class="rex-button" data-variant="primary" data-size="xs" target="_blank" rel="noopener">phpinfo</a>';
        $fragment->setVar('options', $phpinfo, false);
    }

    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}

echo '</div></div>';
