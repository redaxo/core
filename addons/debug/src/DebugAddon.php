<?php

namespace Redaxo\Debug;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;

final class DebugAddon extends Addon
{
    public protected(set) LoadOrder $load = LoadOrder::Early;

    #[Override]
    public function boot(): void
    {
        $this->includeFile('boot.php');
    }

    #[Override]
    public function install(): void
    {
        $this->includeFile('install.php');
    }
}
