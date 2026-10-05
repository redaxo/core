<?php

namespace Redaxo\Debug;

use Clockwork\Clockwork;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Request;
use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;
use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Dir;

use function extension_loaded;
use function is_dir;

final class DebugAddon extends Addon
{
    public protected(set) LoadOrder $load = LoadOrder::Early;

    /** @internal */
    public private(set) VanillaClockwork $clockworkHelper {
        get => $this->clockworkHelper ??= $this->initClockwork();
    }

    /** @internal */
    public Clockwork $clockwork {
        get {
            /** @var Clockwork */
            return $this->clockworkHelper->getClockwork();
        }
    }

    /** @internal */
    public Request $clockworkRequest {
        get {
            /** @var Request */
            return $this->clockwork->getRequest();
        }
    }

    #[Override]
    public function boot(): void
    {
        $this->includeFile('boot.php');
    }

    #[Override]
    public function getPages(): iterable
    {
        if (!Core::isDevMode()) {
            return;
        }

        // reachable via the dev mode marker next to the logo
        yield new Page($this->name, 'Debug')
            ->setRequiredPermissions('admin')
            ->setHasLayout(false);
    }

    #[Override]
    public function install(): void
    {
        $this->includeFile('install.php');
    }

    /** @internal */
    public function ensureClockworkStoragePath(): void
    {
        $storagePath = $this->getClockworkStoragePath();
        if (!is_dir($storagePath)) {
            Dir::create($storagePath);
        }
    }

    private function getClockworkStoragePath(): string
    {
        return $this->getCachePath('clockwork.db');
    }

    private function initClockwork(): VanillaClockwork
    {
        /** @var VanillaClockwork $clockwork */
        $clockwork = VanillaClockwork::init([
            // access is already restricted to admins in dev mode, so don't limit it to local hosts like Clockwork does by default
            'enable' => true,
            'storage_files_path' => $this->getClockworkStoragePath(),
            'storage_files_compress' => true,

            // there is a probability from 1 to 100 that the cleanup mechanism will be triggered and files older than 2 days will be removed
            'storage_expiration' => 60 * 24 * 2,
        ]);
        if (extension_loaded('xdebug')) {
            /** @var Clockwork $instance */
            $instance = $clockwork->getClockwork();
            $instance->addDataSource(new XdebugDataSource());
        }

        return $clockwork;
    }
}
