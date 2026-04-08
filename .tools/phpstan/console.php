<?php

if (!defined('REX_MIN_PHP_VERSION')) {
    unset($REX);
    $REX['REDAXO'] = true;
    $REX['HTDOCS_PATH'] = './';
    $REX['BACKEND_FOLDER'] = 'redaxo';
    $REX['LOAD_PAGE'] = false;

    require dirname(__DIR__, 2) . '/redaxo/src/core/boot.php';

    // initialize autoloader before phpstan autoload wrapper is active
    // https://github.com/redaxo/core/pull/4369#issuecomment-770195916
    class_exists(rex_backend_login::class);
}

$application = new rex_console_application();
$application->setCommandLoader(new rex_console_command_loader());

return $application;
