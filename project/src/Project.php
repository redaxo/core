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

    #[Override]
    public function install(): void
    {
        // Describe the tables the project owns here, e.g. via `Table::get(...)->ensureColumn(...)->ensure()`.
        // Runs on every `console migrate`, after the core and all addons, so it has to be idempotent.
        // For steps that should run exactly once there are migrations: `console migrate:make <description>`.
    }
}
