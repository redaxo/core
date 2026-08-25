<?php

namespace Project;

use Override;
use Redaxo\Core\AbstractProject;

final class Project extends AbstractProject
{
    #[Override]
    public function configure(): void
    {
        // Configuration for the project. Runs on every request,
        // while the core boots and before any addon is loaded.
    }

    #[Override]
    public function boot(): void
    {
        // Runtime initialization for the project. Runs on every request,
        // after the core and all addons have booted.
    }
}
