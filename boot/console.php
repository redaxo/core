<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Console\Application;
use Redaxo\Core\Console\CommandLoader;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;

$project = Core::getProject();

// the console is always english: some of its output is not translated at all, so a mix would be odd
I18n::$defaultLocale = 'en_gb';
I18n::setLocale('en_gb');

$application = new Application($project);
Core::setProperty('console', $application);

Addon::initialize();

foreach (Addon::getBootOrder() as $packageId) {
    Addon::require($packageId)->enlist();
}

$project->enlist();

$application->setCommandLoader(new CommandLoader());

$application->run();
