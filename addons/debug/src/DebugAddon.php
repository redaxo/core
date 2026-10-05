<?php

namespace Redaxo\Debug;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;

final class DebugAddon extends Addon
{
    public protected(set) LoadOrder $load = LoadOrder::Early;

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
        yield new Page($this->name, I18n::msg('debug'))
            ->setRequiredPermissions('admin')
            ->setHasLayout(false);
    }

    #[Override]
    public function install(): void
    {
        $this->includeFile('install.php');
    }
}
