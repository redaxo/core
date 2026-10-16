<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MetaInfo\MetaSync;
use Redaxo\Core\Migration\Migration;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Setup\Setup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_map;
use function array_merge;
use function microtime;
use function sprintf;

/**
 * Applies the `install()` of the core, all addons and the project, then the pending {@see Migration} files.
 *
 * @internal
 */
#[AsCommand(name: 'migrate', description: 'Brings the database in line with the current code')]
final class MigrateCommand extends AbstractCommand implements StandaloneInterface
{
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option('Record pending migrations as executed without running them')] bool $fake = false,
    ): int {
        if ('' !== $error = Setup::checkDbVersion()) {
            $io->error($error);
            return Command::FAILURE;
        }

        if (!$this->convergeSchema($io)) {
            return Command::FAILURE;
        }

        $this->reportOrphans($io);

        $this->syncMetaInfo($input, $io);

        $this->runMigrations($io, $fake);

        Cache::delete();

        $io->success('Migration completed.');
        return Command::SUCCESS;
    }

    /** @return bool `false` if a package failed and the migration has to be aborted */
    private function convergeSchema(SymfonyStyle $io): bool
    {
        $io->section('Schema');

        // without the core's config table, no install() has ever run on this database
        $freshInstance = !Table::get('rex_config')->exists();

        if (!$this->convergePackage($io, Migrator::CORE, static function (): void {
            require Path::core('setup/install.php');
        })) {
            return false;
        }

        if ($freshInstance) {
            // install() created the current schema, so the migrations leading there must not run (addons do the same
            // in their AddonManager::install(), the project is new along with the instance)
            Migrator::baseline(Migrator::CORE);
            Migrator::baseline(Migrator::PROJECT);
        }

        foreach (Addon::getBootOrder() as $addonName) {
            $manager = AddonManager::factory(Addon::require($addonName));

            $converge = static function () use ($manager): void {
                if (!$manager->install()) {
                    throw new UserMessageException($manager->getMessage());
                }
            };

            if (!$this->convergePackage($io, $addonName, $converge)) {
                return false;
            }
        }

        return $this->convergePackage($io, Migrator::PROJECT, static function (): void {
            Core::getProject()->install();
        });
    }

    /** Addons that have data on this instance but are no longer installed via composer. */
    private function reportOrphans(SymfonyStyle $io): void
    {
        $lines = [];
        foreach (AddonManager::getOrphans() as $addon) {
            $blocker = AddonManager::getOrphanRemovalBlocker($addon);
            $lines[] = '  ' . $addon . (null === $blocker ? '' : ' (kept: ' . $blocker . ')');
        }

        if ([] === $lines) {
            return;
        }

        $io->warning([
            'These addons still have data in the database but are no longer installed via composer. `migrate:cleanup` removes it — run it once the new release is live:',
            implode("\n", $lines),
        ]);
    }

    /**
     * @param callable(): void $converge
     * @return bool `false` if the package failed and the migration has to be aborted
     */
    private function convergePackage(SymfonyStyle $io, string $package, callable $converge): bool
    {
        $io->write(sprintf('  %s ... ', $package));

        try {
            $converge();
        } catch (UserMessageException $e) {
            $io->writeln('<error>FAIL</error> ' . $this->decodeMessage($e->getMessage()));
            $io->error('Migration aborted.');

            return false;
        } catch (Throwable $e) {
            $io->writeln('<error>FAIL</error>');

            throw $e;
        }

        $io->writeln('<info>OK</info>');

        return true;
    }

    private function runMigrations(SymfonyStyle $io, bool $fake): void
    {
        $io->section('Migrations');

        $pending = Migrator::getPending();

        if ([] === $pending) {
            $io->writeln('  nothing to migrate');

            return;
        }

        foreach ($pending as $migration) {
            $io->write(sprintf('  %s: %s ... ', $migration->package, $migration->id));

            if ($fake) {
                Migrator::markExecuted($migration);
                $io->writeln('<comment>FAKED</comment>');

                continue;
            }

            $start = microtime(true);

            try {
                Migrator::run($migration);
            } catch (Throwable $e) {
                $io->writeln('<error>FAIL</error>');
                $io->error(sprintf(
                    'Migration "%s" of package "%s" was not recorded and runs again on the next attempt. DDL is not transactional — check for a partial state first.',
                    $migration->id,
                    $migration->package,
                ));

                throw $e;
            }

            $io->writeln(sprintf('<info>OK</info> (%s)', Helper::formatTime(microtime(true) - $start)));
        }
    }

    private function syncMetaInfo(InputInterface $input, SymfonyStyle $io): void
    {
        $io->section('MetaInfo fields');

        $confirmDrop = $input->isInteractive()
            ? static fn (string $table, string $column): bool => $io->confirm(
                sprintf('Obsolete metainfo column "%s" in "%s" has no field anymore. Drop it (the data will be lost)?', $column, $table),
                false,
            )
            : static fn (): bool => false;

        $result = MetaSync::run($confirmDrop);

        foreach ($result['added'] as $column) {
            $io->writeln(sprintf('  added <info>%s</info>', $column));
        }
        foreach ($result['modified'] as $column) {
            $io->writeln(sprintf('  modified <info>%s</info>', $column));
        }
        foreach ($result['dropped'] as $column) {
            $io->writeln(sprintf('  dropped <comment>%s</comment>', $column));
        }

        if ([] !== $result['kept']) {
            $io->warning(array_merge(
                ['Obsolete metainfo columns kept (no field defines them). Run `migrate` interactively to drop them:'],
                array_map(static fn (string $column): string => '  ' . $column, $result['kept']),
            ));
        }
    }
}
