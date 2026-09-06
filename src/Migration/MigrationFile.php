<?php

namespace Redaxo\Core\Migration;

/** A migration file found on disk, identified by the package it belongs to and its file name. */
final readonly class MigrationFile
{
    /**
     * @param non-empty-string $package Name of the owning addon, or {@see Migrator::CORE}/{@see Migrator::PROJECT}
     * @param non-empty-string $id File name without the `.php` extension, e.g. `2026-08-21-143000-add_sku`
     * @param non-empty-string $path Absolute path of the migration file
     */
    public function __construct(
        public string $package,
        public string $id,
        public string $path,
    ) {}
}
