<?php

namespace Redaxo\Debug;

use Override;
use Redaxo\Core\Addon\Addon;

final class DebugAddon extends Addon
{
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
