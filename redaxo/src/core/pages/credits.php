<?php

/** Creditsseite. Auflistung der Credits an die Entwickler von REDAXO und den AddOns. */

echo rex_view::title(rex_i18n::msg('credits'), '');

if (rex_get('license')) {
    $license = rex_markdown::factory()->parse(rex_file::require(rex_path::base('LICENSE.md')));

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'REDAXO ' . rex_i18n::msg('credits_license'));
    $fragment->setVar('body', $license, false);
    echo '<div id="license"></div>'; // scroll anchor
    echo $fragment->parse('core/page/section.php');

    echo '<a class="btn btn-back" href="javascript:history.back();">' . rex_i18n::msg('package_back') . '</a>';

    return;
}

$content = [];

$content[] = '
    <h3>Jan Kristinus <small>jan.kristinus@redaxo.org</small></h3>
    <p>
        ' . rex_i18n::msg('credits_inventor') . ' &amp; ' . rex_i18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>

    <h3>Markus Staab <small>markus.staab@redaxo.org</small></h3>
    <p>' . rex_i18n::msg('credits_developer') . '<br />
        REDAXO, <a href="https://www.redaxo.org" onclick="window.open(this.href); return false;">www.redaxo.org</a>
    </p>

    <h3>Gregor Harlan <small>gregor.harlan@redaxo.org</small></h3>
    <p>' . rex_i18n::msg('credits_developer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>';

$content[] = '
    <h3>Ralph Zumkeller <small>info@redaxo.org</small></h3>
    <p>' . rex_i18n::msg('credits_designer') . '<br />
        Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a>
    </p>

    <h3>Thomas Blum <small>thomas.blum@redaxo.org</small></h3>
    <p>Yakamara Media GmbH &amp; Co. KG, <a href="https://www.yakamara.de" onclick="window.open(this.href); return false;">www.yakamara.de</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/grid.php');

$coreVersion = rex_escape(rex::getVersion());
if (rex_version::isUnstable($coreVersion)) {
    $coreVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . rex_i18n::msg('unstable_version') . '"></i> ' . $coreVersion;
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'REDAXO <small>' . $coreVersion . ' &ndash; <a href="' . rex_url::backendPage('credits', ['license' => 'core']) . '">' . rex_i18n::msg('credits_license') . '</a></small>', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$content = '';

$content .= '

    <table class="table table-hover">
        <thead>
        <tr>
            <th class="rex-table-icon">&nbsp;</th>
            <th>' . rex_i18n::msg('credits_name') . '</th>
            <th class="rex-table-slim">' . rex_i18n::msg('credits_version') . '</th>
            <th colspan="3">' . rex_i18n::msg('credits_information') . '</th>
            <th>' . rex_i18n::msg('credits_author') . '</th>
        </tr>
        </thead>

        <tbody>';

foreach (rex_package::getAvailablePackages() as $package) {
    $helpUrl = rex_url::backendPage('packages', ['subpage' => 'help', 'package' => $package->getPackageId()]);

    $license = '';
    if (is_readable($licenseFile = $package->getPath('LICENSE.md')) || is_readable($licenseFile = $package->getPath('LICENSE'))) {
        $f = fopen($licenseFile, 'r');
        $firstLine = fgets($f) ?: '';
        fclose($f);

        if (preg_match('/^The MIT License(?: \(MIT\))$/i', $firstLine)) {
            $firstLine = 'MIT License';
        }

        $license = '<a href="' . rex_url::backendPage('packages', ['subpage' => 'license', 'package' => $package->getPackageId()]) . '"><i class="rex-icon rex-icon-license"></i> ' . rex_escape($firstLine) . '</a>';
    }

    $packageVersion = rex_escape($package->getVersion());
    if (rex_version::isUnstable($packageVersion)) {
        $packageVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . rex_i18n::msg('unstable_version') . '"></i> ' . $packageVersion;
    }

    $content .= '
            <tr class="rex-package-is-' . $package->getType() . '">
                <td class="rex-table-icon"><i class="rex-icon rex-icon-package-' . $package->getType() . '"></i></td>
                <td data-title="' . rex_i18n::msg('credits_name') . '">' . $package->getName() . ' </td>
                <td data-title="' . rex_i18n::msg('credits_version') . '">' . $packageVersion . '</td>
                <td class="rex-table-slimmer" data-title="' . rex_i18n::msg('credits_help') . '">
                    <a href="' . $helpUrl . '" title="' . rex_i18n::msg('credits_open_help_file') . ' ' . rex_escape($package->getName()) . '"><i class="rex-icon rex-icon-help"></i> ' . rex_i18n::msg('credits_help') . ' <span class="sr-only">' . rex_escape($package->getName()) . '</span></a>
                </td>
                <td class="rex-table-slim" data-title="' . rex_i18n::msg('credits_supportpage') . '">';
    if ($supportpage = $package->getSupportPage()) {
        $content .= '<a href="' . $supportpage . '" onclick="window.open(this.href); return false;"><i class="rex-icon rex-icon-external-link"></i> ' . rex_i18n::msg('credits_supportpage') . '</a>';
    }
    $content .= '
                </td>
                <td class="rex-table-width-6" data-title="' . rex_i18n::msg('credits_license') . '">' . $license . '</td>
                <td data-title="' . rex_i18n::msg('credits_author') . '">' . rex_escape((string) $package->getAuthor()) . '</td>
            </tr>';
}

$content .= '
        </tbody>
    </table>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('credits_caption'), false);
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
