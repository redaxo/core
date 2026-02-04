<?php

declare(strict_types=1);

// total 6 errors

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_media::$instances (array<class-string, array<string, static(rex_media)|null>>) does not accept array<class-string, array<string, rex_media|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_media_category::$instances (array<class-string, array<string, static(rex_media_category)|null>>) does not accept array<class-string, array<string, rex_media_category|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/mediapool/lib/media_category.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_structure_element::$instances (array<class-string, array<string, static(rex_structure_element)|null>>) does not accept array<class-string, array<string, rex_structure_element|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/addons/structure/lib/structure_element.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_user::$instances (array<class-string, array<string, static(rex_user)|null>>) does not accept array<class-string, array<string, rex_user|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/login/user.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_sql_table::$instances (array<class-string, array<string, static(rex_sql_table)|null>>) does not accept array<class-string, array<string, rex_sql_table|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/lib/sql/table.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Static property rex_test_instance_pool_base::$instances (array<class-string, array<string, static(rex_test_instance_pool_base)|null>>) does not accept array<class-string, array<string, rex_test_instance_pool_base|null>>.',
    'count' => 1,
    'path' => __DIR__ . '/../../../redaxo/src/core/tests/base/instance_pool_trait_test.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
