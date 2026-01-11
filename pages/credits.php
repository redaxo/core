<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Markdown;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

/**
 * Creditsseite. Auflistung der Credits an die Entwickler von REDAXO und den AddOns.
 */

echo View::title(I18n::msg('credits'), '');

if (Request::get('license')) {
    $license = Markdown::factory()->parse(File::require(Path::base('LICENSE.md')));

    $fragment = new Fragment();
    $fragment->setVar('title', 'REDAXO ' . I18n::msg('credits_license'));
    $fragment->setVar('body', $license, false);
    echo '<div id="license"></div>'; // scroll anchor
    echo $fragment->parse('core/page/section.php');

    echo '<a class="btn btn-back" href="javascript:history.back();">' . I18n::msg('package_back') . '</a>';

    return;
}

$content = [];

$content[] = '
    <h3>Jan Kristinus <small>jan.kristinus@redaxo.org</small></h3>
    <p>
        ' . I18n::msg('credits_inventor') . ' &amp; ' . I18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>

    <h3>Markus Staab <small>markus.staab@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_developer') . '<br />
        REDAXO, <a href="https://www.redaxo.org" onclick="window.open(this.href); return false;">www.redaxo.org</a>
    </p>

    <h3>Gregor Harlan <small>gregor.harlan@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>';

$content[] = '
    <h3>Ralph Zumkeller <small>info@redaxo.org</small></h3>
    <p>' . I18n::msg('credits_designer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>

    <h3>Thomas Blum <small>thomas.blum@redaxo.org</small></h3>
    <p>Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a></p>';

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/grid.php');

$coreVersion = escape(Core::getVersion());
if (Version::isUnstable($coreVersion)) {
    $coreVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $coreVersion;
}

$fragment = new Fragment();
$fragment->setVar('title', 'REDAXO <small>' . $coreVersion . ' &ndash; <a href="' . Url::backendPage('credits', ['license' => 'core']) . '">' . I18n::msg('credits_license') . '</a></small>', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$content = '';

$content .= '

    <table class="table table-hover">
        <thead>
        <tr>
            <th class="rex-table-icon">&nbsp;</th>
            <th>' . I18n::msg('credits_name') . '</th>
            <th class="rex-table-slim">' . I18n::msg('credits_version') . '</th>
            <th colspan="3">' . I18n::msg('credits_information') . '</th>
            <th>' . I18n::msg('credits_author') . '</th>
        </tr>
        </thead>

        <tbody>';

foreach (Addon::getAvailableAddons() as $package) {
    $helpUrl = Url::backendPage('packages', ['subpage' => 'help', 'package' => $package->getPackageId()]);

    $license = '';
    if (is_readable($licenseFile = $package->getPath('LICENSE.md')) || is_readable($licenseFile = $package->getPath('LICENSE'))) {
        $f = fopen($licenseFile, 'r');
        $firstLine = fgets($f) ?: '';
        fclose($f);

        if (preg_match('/^The MIT License(?: \(MIT\))$/i', $firstLine)) {
            $firstLine = 'MIT License';
        }

        $license = '<a href="' . Url::backendPage('packages', ['subpage' => 'license', 'package' => $package->getPackageId()]) . '"><i class="rex-icon rex-icon-license"></i> ' . escape($firstLine) . '</a>';
    }

    $packageVersion = escape($package->getVersion());
    if (Version::isUnstable($packageVersion)) {
        $packageVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $packageVersion;
    }

    $content .= '
            <tr class="rex-package-is-addon">
                <td class="rex-table-icon"><i class="rex-icon rex-icon-package-addon"></i></td>
                <td data-title="' . I18n::msg('credits_name') . '">' . $package->getName() . ' </td>
                <td data-title="' . I18n::msg('credits_version') . '">' . $packageVersion . '</td>
                <td class="rex-table-slimmer" data-title="' . I18n::msg('credits_help') . '">
                    <a href="' . $helpUrl . '" title="' . I18n::msg('credits_open_help_file') . ' ' . escape($package->getName()) . '"><i class="rex-icon rex-icon-help"></i> ' . I18n::msg('credits_help') . ' <span class="sr-only">' . escape($package->getName()) . '</span></a>
                </td>
                <td class="rex-table-slim" data-title="' . I18n::msg('credits_supportpage') . '">';
    if ($supportpage = $package->getSupportPage()) {
        $content .= '<a href="' . $supportpage . '" onclick="window.open(this.href); return false;"><i class="rex-icon rex-icon-external-link"></i> ' . I18n::msg('credits_supportpage') . '</a>';
    }
    $content .= '
                </td>
                <td class="rex-table-width-6" data-title="' . I18n::msg('credits_license') . '">' . $license . '</td>
                <td data-title="' . I18n::msg('credits_author') . '">' . escape((string) $package->getAuthor()) . '</td>
            </tr>';
}

$content .= '
        </tbody>
    </table>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('credits_caption'), false);
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
