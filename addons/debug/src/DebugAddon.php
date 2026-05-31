<?php

namespace Redaxo\Debug;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Backend\MainPage;
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
        if (Core::isLiveMode()) {
            return;
        }

        yield new MainPage('system', $this->name, I18n::msg('debug'))
            ->setRequiredPermissions('admin')
            ->setIcon('rex-icon rex-icon-heartbeat')
            ->setLinkAttr('target', '_blank');
    }

    #[Override]
    public function install(): void
    {
        $this->includeFile('install.php');
    }
}
