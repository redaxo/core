<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Version;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($markdown) && is_callable($markdown));

$package = Addon::get('install');

$core = Request::request('core', 'boolean');
$addonkey = Request::request('addonkey', 'string');

$coreVersions = [];
$addons = [];

$message = ApiFunction::getMessage();

try {
    $coreVersions = rex_api_install_core_update::getVersions();
    $addons = rex_install_packages::getUpdatePackages();

    $config = File::getCache(Path::addonData('install', 'config.json'), []);
    if (isset($config['api_login']) && $config['api_login'] && isset($config['api_key'])) {
        echo Message::info($package->i18n('install_info_myredaxo'));
    }
} catch (UserMessageException $e) {
    $message .= Message::warning($e->getMessage());
    $addonkey = '';
}

if ($core && !empty($coreVersions)) {
    $panel = '
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th class="rex-table-icon">&nbsp;</th>
                <th>' . $package->i18n('version') . '</th>
                <th>' . $package->i18n('description') . '</th>
                <th class="rex-table-action"></th>
            </tr>
            </thead>
            <tbody>';

    $latestRelease = false;
    foreach ($coreVersions as $id => $file) {
        $releaseLabel = '';
        $confirm = '';
        $packageIcon = '<i class="rex-icon rex-icon-package"></i>';
        $version = escape($file['version']);
        $description = $markdown($file['description']);

        if (Version::isUnstable($version)) {
            $releaseLabel = '<br><span class="label label-warning" title="' . I18n::msg('unstable_version') . '">' . I18n::msg('unstable_version') . '</span> ';
            $confirm = ' data-confirm="' . I18n::msg('install_download_unstable') . '"';
            $packageIcon = '<i class="rex-icon rex-icon-unstable-version"></i>';
        } elseif (!$latestRelease) {
            $releaseLabel = '<br><span class="label label-success">' . I18n::msg('install_latest_release') . '</span>';
            $latestRelease = true;
        }

        $panel .= '
                <tr data-pjax-scroll-to="0">
                    <td class="rex-table-icon">' . $packageIcon . '</td>
                    <td data-title="' . $package->i18n('version') . '">' . $version . $releaseLabel . '</td>
                    <td data-title="' . $package->i18n('description') . '">' . $description . '</td>
                    <td class="rex-table-action"><a' . $confirm . ' class="rex-link-expanded" href="' . Url::currentBackendPage(['core' => 1, 'version_id' => $id] + rex_api_install_core_update::getUrlParams()) . '" data-pjax="false">' . $package->i18n('update') . '</a></td>
                </tr>';
    }

    $panel .= '</tbody></table>';

    $fragment = new Fragment();
    $fragment->setVar('title', 'REDAXO Core', false);
    $fragment->setVar('content', $panel, false);
    $content = $fragment->parse('core/page/section.php');
} elseif ($addonkey && isset($addons[$addonkey])) {
    $addon = $addons[$addonkey];

    $version = escape(Addon::get($addonkey)->getVersion());
    if (Version::isUnstable($version)) {
        $version = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $version;
    }

    $panel = '
        <table class="table">
            <tbody>
            <tr>
                <th class="rex-table-width-5">' . $package->i18n('name') . '</th>
                <td data-title="' . $package->i18n('name') . '">' . escape($addon['name']) . '</td>
            </tr>
            <tr>
                <th>' . $package->i18n('existing_version') . '</th>
                <td data-title="' . $package->i18n('existing_version') . '">' . $version . '</td>
            </tr>
            <tr>
                <th>' . $package->i18n('author') . '</th>
                <td data-title="' . $package->i18n('author') . '">' . escape($addon['author']) . '</td>
            </tr>
            <tr>
                <th>' . $package->i18n('shortdescription') . '</th>
                <td data-title="' . $package->i18n('shortdescription') . '">' . nl2br(escape($addon['shortdescription'])) . '</td>
            </tr>
            <tr>
                <th>' . $package->i18n('description') . '</th>
                <td data-title="' . $package->i18n('description') . '">' . nl2br(escape($addon['description'])) . '</td>
            </tr>';

    if ($addon['website']) {
        $panel .= '
            <tr>
                <th>' . $package->i18n('website') . '</th>
                <td data-title="' . $package->i18n('website') . '"><a class="rex-link-expanded" rel="noopener noreferrer" href="' . escape($addon['website']) . '" target="_blank">' . escape($addon['website']) . ' <i class="fa fa-external-link"></i></a></td>
            </tr>';
    }

    $panel .= '
            </tbody>
        </table>';

    $fragment = new Fragment();
    $fragment->setVar('title', '<b>' . escape($addonkey) . '</b> ' . $package->i18n('information'), false);
    $fragment->setVar('content', $panel, false);
    $content = $fragment->parse('core/page/section.php');

    $panel = '
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th class="rex-table-icon"></th>
                <th class="rex-table-width-4">' . $package->i18n('version') . '</th>
                <th>' . $package->i18n('description') . '</th>
                <th class="rex-table-action"></th>
            </tr>
            </thead>
            <tbody>';

    $latestRelease = false;
    foreach ($addon['files'] as $fileId => $file) {
        $releaseLabel = '';
        $confirm = '';
        $packageIcon = '<i class="rex-icon rex-icon-package"></i>';
        $version = escape($file['version']);
        $description = $markdown($file['description']);

        if (Version::isUnstable($version)) {
            $releaseLabel = '<br><span class="label label-warning" title="' . I18n::msg('unstable_version') . '">' . I18n::msg('unstable_version') . '</span> ';
            $confirm = ' data-confirm="' . I18n::msg('install_download_unstable') . '"';
            $packageIcon = '<i class="rex-icon rex-icon-unstable-version"></i>';
        } elseif (!$latestRelease) {
            $releaseLabel = '<br><span class="label label-success">' . I18n::msg('install_latest_release') . '</span>';
            $latestRelease = true;
        }

        $panel .= '
            <tr>
                <td class="rex-table-icon">' . $packageIcon . '</td>
                <td data-title="' . $package->i18n('version') . '">' . $version . $releaseLabel . '</td>
                <td data-title="' . $package->i18n('description') . '">' . $description . '</td>
                <td class="rex-table-action"><a' . $confirm . ' class="rex-link-expanded" href="' . Url::currentBackendPage(['addonkey' => $addonkey, 'file' => $fileId] + rex_api_install_package_update::getUrlParams()) . '" data-pjax="false">' . $package->i18n('update') . '</a></td>
            </tr>';
    }

    $panel .= '</tbody></table>';

    $fragment = new Fragment();
    $fragment->setVar('title', $package->i18n('files'), false);
    $fragment->setVar('content', $panel, false);
    $content .= $fragment->parse('core/page/section.php');
} else {
    $panel = '
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['func' => 'reload']) . '" title="' . $package->i18n('reload') . '"><i class="rex-icon rex-icon-refresh"></i></a></th>
                <th>' . $package->i18n('key') . '</th>
                <th>' . $package->i18n('name') . '</th>
                <th>' . $package->i18n('existing_version') . '</th>
                <th>' . $package->i18n('available_versions') . '</th>
            </tr>
            </thead>
            <tbody>';

    if (!empty($coreVersions)) {
        $availableVersions = [];
        foreach ($coreVersions as $file) {
            $availVers = escape($file['version']);
            if (Version::isUnstable($availVers)) {
                $availVers = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $availVers;
                $availableVersions[] = '<span class="label label-warning">' . $availVers . '</span> ';
            } else {
                $availableVersions[] = '<span class="label label-success">' . $availVers . '</span> ';
            }
        }
        $url = Url::currentBackendPage(['core' => 1]);

        $coreVersion = escape(Core::getVersion());
        if (Version::isUnstable($coreVersion)) {
            $coreVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $coreVersion;
        }

        $panel .= '
            <tr>
                <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $url . '"><i class="rex-icon rex-icon-package"></i></a></td>
                <td data-title="' . $package->i18n('key') . '"><a class="rex-link-expanded" href="' . $url . '">core</a></td>
                <td data-title="' . $package->i18n('name') . '">REDAXO Core</td>
                <td data-title="' . $package->i18n('existing_version') . '">' . $coreVersion . '</td>
                <td data-title="' . $package->i18n('available_versions') . '">' . implode('', $availableVersions) . '</td>
            </tr>';
    }

    foreach ($addons as $key => $addon) {
        $availableVersions = [];
        foreach ($addon['files'] as $file) {
            $availVers = escape($file['version']);
            if (Version::isUnstable($availVers)) {
                $availVers = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $availVers;
                $availableVersions[] = '<span class="label label-warning" title="' . escape(Formatter::intlDate($file['created'])) . '">' . $availVers . '</span> ';
            } else {
                $availableVersions[] = '<span class="label label-success" title="' . escape(Formatter::intlDate($file['created'])) . '">' . $availVers . '</span> ';
            }
        }

        $url = Url::currentBackendPage(['addonkey' => $key]);

        $packageVersion = escape(Addon::get($key)->getVersion());
        if (Version::isUnstable($packageVersion)) {
            $packageVersion = '<i class="rex-icon rex-icon-unstable-version" title="' . I18n::msg('unstable_version') . '"></i> ' . $packageVersion;
        }

        $panel .= '
            <tr>
                <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $url . '"><i class="rex-icon rex-icon-package"></i></a></td>
                <td data-title="' . $package->i18n('key') . '"><a class="rex-link-expanded" href="' . $url . '">' . escape($key) . '</a></td>
                <td data-title="' . $package->i18n('name') . '">' . escape($addon['name']) . '</td>
                <td data-title="' . $package->i18n('existing_version') . '">' . $packageVersion . '</td>
                <td data-title="' . $package->i18n('available_versions') . '">' . implode('', $availableVersions) . '</td>
            </tr>';
    }

    $panel .= '</tbody></table>';

    $fragment = new Fragment();
    $fragment->setVar('title', $package->i18n('available_updates', ($coreVersions ? 1 : 0) + count($addons)), false);
    $fragment->setVar('content', $panel, false);
    $content = $fragment->parse('core/page/section.php');
}

echo $message;
echo $content;
