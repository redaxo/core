<?php

set_time_limit(0);

// setup a minimal exception handler to print early errors,
// happening before redaxo itself was able to register its rex_error_handler
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, $exception->getTraceAsString() . "\n");
    exit(254);
});

require __DIR__ . '/boot.php';

// force debug mode to enable output of notices/warnings and dump() function
rex::setProperty('debug', true);

rex::setProperty('lang', 'en_gb');
rex_i18n::setLocale('en_gb');

$application = new rex_console_application();
rex::setProperty('console', $application);

rex_addon::initialize(!rex::isSetup());

if (!rex::isSetup()) {
    foreach (rex::getPackageOrder() as $packageId) {
        rex_package::require($packageId)->enlist();
    }
}

$application->setCommandLoader(new rex_console_command_loader());

// Override default list command to display information, that more commands are available after setup.
$command = new rex_command_list();
$application->addCommand($command);
$application->setDefaultCommand($command->getName());

$application->run();
