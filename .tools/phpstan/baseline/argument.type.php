<?php

declare(strict_types=1);

// total 35 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $array of function array_diff expects an array of values castable to string, array<array|bool|float|int|string|null> given.',
    'count' => 2,
    'path' => __DIR__ . '/../../../redaxo/src/addons/debug/boot.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $arrays of function array_diff expects an array of values castable to string, array<array|bool|float|int|string|null> given.',
    'count' => 2,
    'path' => __DIR__ . '/../../../redaxo/src/addons/debug/boot.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_media::getInstance() expects (callable(mixed ...): (static|null))|null, Closure(mixed): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_media::getInstanceList() expects callable(mixed ...): (static|null), Closure(string): static given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_media_category::getInstance() expects (callable(mixed ...): (static|null))|null, Closure(mixed): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media_category.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_media_category::getInstanceList() expects callable(mixed ...): (rex_media|null), Closure(string): (rex_media|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media_category.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_media_category::getInstanceList() expects callable(mixed ...): (static|null), Closure(int): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media_category.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $createListCallback of static method rex_media_category::getInstanceList() expects (callable(mixed ...): array<mixed>)|null, Closure(mixed): array<mixed> given.',
    'count' => 2,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media_category.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $extensionPoint of static method rex_extension::registerPoint() expects rex_extension_point<string|null>, rex_extension_point<string|null> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/service_media.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_structure_element::getInstance() expects (callable(mixed ...): (static|null))|null, Closure(mixed, mixed): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/lib/structure_element.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_structure_element::getInstanceList() expects callable(mixed ...): (static|null), Closure(mixed): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/lib/structure_element.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $createListCallback of static method rex_structure_element::getInstanceList() expects (callable(mixed ...): array<mixed>)|null, Closure(mixed, mixed): array<mixed> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/lib/structure_element.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $replace of function str_replace expects array<string>|string, list<int> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/plugins/content/lib/article_action.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $replace of function str_replace expects array<string>|string, array<int, int|string> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/plugins/content/lib/article_content_base.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of function spl_autoload_register expects (callable(string): void)|null, Closure(string): bool given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/autoload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $options of method rex_password_policy::__construct() expects array<string, array{min?: int, max?: int}>, array{no_reuse_of_last?: int, no_reuse_within?: string, force_renew_after?: string, block_account_after?: string} given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/login/backend_password_policy.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_user::getInstance() expects (callable(mixed ...): (static|null))|null, Closure(int): (static|null) given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/login/user.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_sql_table::getInstance() expects (callable(mixed ...): (static|null))|null, Closure(int, string): static given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/sql/table.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $extensionPoint of static method rex_extension::registerPoint() expects rex_extension_point<string|null>, rex_extension_point<string|null> given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/util/editor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $callback of function set_error_handler expects (callable(int, string, string, int): bool)|null, Closure(mixed, mixed): void given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/util/socket/socket.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_test_instance_list_pool::getInstanceList() expects callable(mixed ...): (object|null), Closure(mixed, mixed): void given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_list_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $getInstanceCallback of static method rex_test_instance_list_pool::getInstanceList() expects callable(mixed ...): (rex_test_instance_list_pool|null), Closure(int): rex_test_instance_list_pool given.',
    'count' => 4,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_list_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $createListCallback of static method rex_test_instance_list_pool::getInstanceList() expects (callable(mixed ...): array<mixed>)|null, Closure(mixed): array{1, 2} given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_list_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #3 $createListCallback of static method rex_test_instance_list_pool::getInstanceList() expects (callable(mixed ...): array<mixed>)|null, Closure(mixed, mixed): array{} given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_list_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_test_instance_pool_base::getInstance() expects (callable(mixed ...): (rex_test_instance_pool_1|null))|null, Closure(mixed): rex_test_instance_pool_1 given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_test_instance_pool_base::getInstance() expects (callable(mixed ...): (rex_test_instance_pool_1|null))|null, Closure(mixed, mixed): void given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #2 $createCallback of static method rex_test_instance_pool_base::getInstance() expects (callable(mixed ...): (rex_test_instance_pool_2|null))|null, Closure(mixed): rex_test_instance_pool_2 given.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_pool_trait_test.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Parameter #1 $extensionPoint of static method rex_extension::registerPoint() expects rex_extension_point<string|null>, rex_extension_point<string|null> given.',
    'count' => 2,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/extension_test.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
