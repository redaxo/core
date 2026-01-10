<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Console\Application;
use Redaxo\Core\Console\Command\ListCommand;
use Redaxo\Core\Console\CommandLoader;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;

// force debug mode to enable output of notices/warnings and dump() function
Core::setProperty('debug', true);

Core::setProperty('lang', 'en_gb');
I18n::setLocale('en_gb');

$application = new Application();
Core::setProperty('console', $application);

Addon::initialize(!Core::isSetup());

if (!Core::isSetup()) {
    foreach (Core::getPackageOrder() as $packageId) {
        Addon::require($packageId)->enlist();
    }
}

$application->setCommandLoader(new CommandLoader());

// Override default list command to display information, that more commands are available after setup.
$command = new ListCommand();
$application->add($command);
$application->setDefaultCommand($command->getName());

$application->run();
