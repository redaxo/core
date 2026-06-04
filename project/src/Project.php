<?php

namespace Project;

use Override;
use Redaxo\Core\AbstractProject;

final class Project extends AbstractProject
{
    #[Override]
    public function boot(): void
    {
        // Runtime initialization for the project. Runs on every request,
        // after the core and all addons have booted.
    }
}
