<?php

declare(strict_types=1);

// total 19 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_api_install_core_update::messageFromPackage() has parameter $manager with generic class rex_package_manager but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/install/lib/api/api_core_update.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_media_manager::mediaIsInUse() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/media_manager/lib/media_manager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_media_manager::mediaUpdated() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/media_manager/lib/media_manager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Function rex_metainfo_cleanup() has parameter $epOrParams with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/extensions/extension_cleanup.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Function rex_metainfo_extensions_handler() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/functions/function_metainfo.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_article_handler::extendForm() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/article_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_category_handler::extendForm() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/category_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_category_handler::renderToggleButton() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/category_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_clang_handler::extendForm() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/clang_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_clang_handler::renderToggleButton() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/clang_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_handler::extendForm() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_media_handler::extendForm() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/media_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_metainfo_media_handler::isMediaInUse() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/handler/media_handler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_input::factory() return type with generic class rex_input does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/input.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'PHPDoc tag @var for variable $class contains generic class rex_input but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/metainfo/lib/input.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_user_role::removeOrReplaceItem() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/users/lib/role.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_form_prio_element::organizePriorities() has parameter $ep with generic class rex_extension_point but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/form/elements/prio.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_package_manager::factory() return type with generic class rex_package_manager does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/packages/manager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Method rex_package_manager::setFactoryClass() has parameter $subclass with generic class rex_package_manager but does not specify its types: T',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/packages/manager.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
