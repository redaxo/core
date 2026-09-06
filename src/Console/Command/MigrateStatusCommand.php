<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Migration\MigrationFile;
use Redaxo\Core\Migration\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_map;
use function count;
use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'migrate:status', description: 'Lists pending migrations and exits with an error if there are any')]
final class MigrateStatusCommand extends AbstractCommand implements StandaloneInterface
{
    public function __invoke(SymfonyStyle $io): int
    {
        $pending = Migrator::getPending();

        if ([] === $pending) {
            $io->success('No pending migrations.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Package', 'Migration'],
            array_map(static fn (MigrationFile $migration): array => [$migration->package, $migration->id], $pending),
        );

        // Non-zero on purpose, so a deployment or CI job can gate on "the database is behind the code".
        $io->warning(sprintf('%d pending migration(s). Run `migrate` to execute them.', count($pending)));

        return Command::FAILURE;
    }
}
