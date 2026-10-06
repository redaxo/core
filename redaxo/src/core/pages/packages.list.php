<?php

echo rex_view::title(rex_i18n::msg('addons'), '');

// the package manager don't know new packages in the addon folder
// so we need to make them available
rex_package_manager::synchronizeWithFileSystem();

$fragment = new rex_fragment();
$fragment->setVar('id', 'rex-js-available-addon-search');
$fragment->setVar('autofocus', !rex_request('function', 'bool'));
$toolbar = $fragment->parse('core/form/search.php');

$content = '
        <table class="table table-hover rex-targeted-rows" id="rex-js-table-available-packages-addons">
        <thead>
            <tr>
                <th class="rex-table-icon">&nbsp;</th>
                <th>' . rex_i18n::msg('package_hname') . '</th>
                <th class="rex-table-slim">' . rex_i18n::msg('package_hversion') . '</th>
                <th colspan="2">' . rex_i18n::msg('package_hinformation') . '</th>
                <th class="rex-table-action">' . rex_i18n::msg('package_hinstall') . '</th>
                <th class="rex-table-action">' . rex_i18n::msg('package_hactive') . '</th>
                <th class="rex-table-action" colspan="2">' . rex_i18n::msg('package_hdelete') . '</th>
            </tr>
        </thead>
        <tbody>';

$getLink = static function (rex_package $package, $function, $icon = '', $confirm = false, $key = null) {
    $onclick = '';
    if ($confirm) {
        $onclick = ' data-confirm="' . rex_i18n::msg($package->getType() . '_' . $function . '_question', $package->getName()) . '"';
    }
    $text = rex_i18n::msg('package_' . ($key ?: $function));
    $url = rex_url::currentBackendPage([
        'package' => $package->getPackageId(),
        'function' => $function,
    ] + rex_api_package::getUrlParams());

    $icon = ('' != $icon) ? '<i class="rex-icon ' . $icon . '"></i>' : '';
    return '<a class="rex-link-expanded" href="' . $url . '"' . $onclick . ' data-pjax="false">' . $icon . ' ' . $text . '</a>';
};

$getTableRow = static function (rex_package $package) use ($getLink) {
    $packageId = $package->getPackageId();
    $type = $package->getType();

    $delete = $package->isSystemPackage() ? '<small class="text-muted">' . rex_i18n::msg($type . '_system' . $type) . '</small>' : $getLink($package, 'delete', 'rex-icon-package-delete', true);

    $uninstall = '&nbsp;';
    if ($package->isInstalled()) {
        $install = $getLink($package, 'install', 'rex-icon-package-is-installed', false, 'reinstall');
        $uninstall = $getLink($package, 'uninstall', 'rex-icon-package-uninstall', true);
    } else {
        $install = $getLink($package, 'install', 'rex-icon-package-not-installed');
        // $uninstall = rex_i18n::msg('package_notinstalled');
    }

    $class = '';
    $status = '&nbsp;';
    if ($package->isAvailable()) {
        $status = $getLink($package, 'deactivate', 'rex-icon-package-is-activated');
        $class .= ' rex-package-is-activated';
    } elseif ($package->isInstalled()) {
        $status = $getLink($package, 'activate', 'rex-icon-package-not-activated');
        $class .= ' rex-package-is-installed';
    } else {
        $class .= ' rex-package-not-installed';
    }
    $name = '<span class="rex-' . $type . '-name">' . rex_escape($package->getName()) . '</span>';

    $class .= $package->isSystemPackage() ? ' rex-system-' . $type : '';

    // --------------------------------------------- API MESSAGES
    if (($package->getPackageId() == rex_get('package', 'string') && rex_api_function::hasMessage()) || ($package->getPackageId() == rex_get('mark', 'string'))) {
        $class = ' mark';
    }

    $version = '';
    if ('' !== trim($package->getVersion())) {
        $version = ' <span class="rex-' . $type . '-version">' . rex_escape(trim($package->getVersion())) . '</span>';

        if (rex_version::isUnstable($package->getVersion())) {
            $version = '<i class="rex-icon rex-icon-unstable-version" title="' . rex_i18n::msg('unstable_version') . '"></i> ' . $version;
        }
    }

    $license = $package->getLicense();
    if ($license) {
        $license = '<a class="rex-link-expanded" href="' . rex_url::currentBackendPage(['subpage' => 'license', 'package' => $packageId]) . '" data-pjax-scroll-to="0"><i class="rex-icon rex-icon-license"></i> ' . rex_escape($license) . '</a>';
    }

    return '
                <tr id="package-' . rex_escape(rex_string::normalize($packageId, '-', '_')) . '" class="rex-package-is-' . $type . $class . '">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-package-' . $type . '"></i></td>
                    <td data-title="' . rex_i18n::msg('package_hname') . '">' . $name . '</td>
                    <td data-title="' . rex_i18n::msg('package_hversion') . '">' . $version . '</td>
                    <td class="rex-table-slim" data-title="' . rex_i18n::msg('package_hhelp') . '">
                        <a class="rex-link-expanded" href="' . rex_url::currentBackendPage(['subpage' => 'help', 'package' => $packageId]) . '" data-pjax-scroll-to="0" title="' . rex_i18n::msg('package_help') . ' ' . rex_escape($package->getName()) . '"><i class="rex-icon rex-icon-help"></i> ' . rex_i18n::msg('package_hhelp') . ' <span class="sr-only">' . rex_escape($package->getName()) . '</span></a>
                    </td>
                    <td class="rex-table-width-6" data-title="' . rex_i18n::msg('package_hlicense') . '">' . ($license ?? '') . '</td>
                    <td class="rex-table-action">' . $install . '</td>
                    <td class="rex-table-action">' . $status . '</td>
                    <td class="rex-table-action">' . $uninstall . '</td>
                    <td class="rex-table-action">' . $delete . '</td>
                </tr>' . "\n   ";
};

foreach (rex_addon::getRegisteredAddons() as $addon) {
    $content .= $getTableRow($addon);

    if ($addon->isAvailable()) {
        foreach ($addon->getRegisteredPlugins() as $plugin) {
            $content .= $getTableRow($plugin);
        }
    }
}

$content .= '</tbody>
        </table>';

$content .= '
    <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
    <!--
    jQuery(function($) {
        var table = $("#rex-js-table-available-packages-addons");
        var tablebody = table.find("tbody");

        $("#rex-js-available-addon-search .form-control").keyup(function () {
            table.find("tr").show();
            var search = $(this).val().toLowerCase();
            if (search) {
                table.find("tbody tr").each(function () {
                    var tr = $(this);
                    if (tr.text().toLowerCase().indexOf(search) < 0) {
                        tr.hide();
                    }
                });
            }
        });
    });
    rex_searchfield_init("#rex-js-available-addon-search");
    //-->
    </script>
';

echo rex_api_function::getMessage();

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('package_caption'), false);
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
