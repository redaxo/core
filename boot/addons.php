<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Util\Timer;

$project = Core::getProject();

Addon::initialize();

$packageOrder = Addon::getBootOrder();

// in the first run, we register all folders for class- and fragment-loading,
// so it is transparent in which order the addons are included afterwards.
foreach ($packageOrder as $packageId) {
    Addon::require($packageId)->enlist();
}

$project->enlist();

// now we actually include the addons logic
Timer::measure('packages_boot', static function () use ($packageOrder) {
    foreach ($packageOrder as $packageId) {
        Timer::measure('package_boot: ' . $packageId, static function () use ($packageId) {
            Addon::require($packageId)->boot();
        });
    }
});

Extension::registerByAttribute($project);

// ----- all addons configs included
Extension::dispatch(new ExtensionPoint('PACKAGES_INCLUDED'));

$project->boot();
