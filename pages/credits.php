<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

/** Creditsseite. Auflistung der Credits an die Entwickler von REDAXO und den AddOns. */

echo View::title(I18n::msg('credits'), '');

$content = [];

$content[] = '
    <h3>Jan Kristinus <small>jan.kristinus@redaxo.org</small></h3>
    <p>
        ' . I18n::msg('credits_inventor') . ' &amp; ' . I18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" target="_blank" rel="noopener">www.yakamara.de</a>
    </p>

    <h3>Markus Staab <small>markus.staab@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_developer') . '<br />
        REDAXO, <a href="https://www.redaxo.org" target="_blank" rel="noopener">www.redaxo.org</a>
    </p>

    <h3>Gregor Harlan <small>gregor.harlan@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" target="_blank" rel="noopener">www.yakamara.de</a>
    </p>';

$content[] = '
    <h3>Ralph Zumkeller <small>info@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_designer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" target="_blank" rel="noopener">www.yakamara.de</a>
    </p>

    <h3>Thomas Blum <small>thomas.blum@redaxo.org</small></h3>
    <p>Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" target="_blank" rel="noopener">www.yakamara.de</a></p>';

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/grid.php');

$coreVersion = escape(Core::getVersion());
if (Version::isUnstable($coreVersion)) {
    $coreVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '" role="img"></i> ' . $coreVersion;
}

$fragment = new Fragment();
$fragment->setVar('title', 'REDAXO <small>' . $coreVersion . ' &ndash; ' . I18n::msg('credits_license') . ': MIT</small>', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$content = (string) new Table([
    new TableColumn(Html::raw('&nbsp;'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-package-addon" aria-hidden="true"></i>'), ColumnType::Icon),
    new TableColumn(I18n::rawMsg('credits_name'), static fn (Addon $package): string => $package->name),
    new TableColumn(I18n::rawMsg('credits_version'), static function (Addon $package): Html {
        $version = escape($package->getVersion());
        return Html::raw(Version::isUnstable($package->getVersion()) ? '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '" role="img"></i> ' . $version : $version);
    }, attributes: new HtmlAttributes(['class' => ['rex-table-slim']])),
    new TableColumn(I18n::rawMsg('credits_supportpage'), static fn (Addon $package): TableCell => new TableCell(
        ($supportpage = $package->getSupportPage())
            ? Html::raw('<a href="' . escape($supportpage) . '" target="_blank" rel="noopener"><i class="rex-icon rex-icon-external-link" aria-hidden="true"></i> ' . I18n::msg('credits_supportpage') . '</a>')
            : null,
        attributes: new HtmlAttributes(['class' => ['rex-table-slim']]),
    )),
    new TableColumn(I18n::rawMsg('credits_license'), static fn (Addon $package): TableCell => new TableCell((string) $package->getLicense(), attributes: new HtmlAttributes(['class' => ['rex-table-width-6']]))),
    new TableColumn(I18n::rawMsg('credits_author'), static fn (Addon $package): string => (string) $package->getAuthor()),
], Addon::getAll(), attributes: new HtmlAttributes(['data-hover' => true]))->render();

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('credits_caption'), false);
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
