<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MetaInfo\MetaSync;
use Redaxo\Core\Migration\Migration;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;
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
 * Applies the `install()` of the core, all installed addons and the project, then the pending
 * {@see Migration} files.
 *
 * Deactivated addons are covered too, so their schema does not fall behind — but a failing one is only reported,
 * not fatal: it is not booted anyway, so it must not block a deployment.
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
        // verify the database server meets the minimum version requirements
        $sql = Sql::factory();
        $dbType = $sql->getDbType();
        $dbVersion = $sql->getDbVersion();
        $minVersion = Sql::MARIADB === $dbType ? Setup::MIN_MARIADB_VERSION : Setup::MIN_MYSQL_VERSION;
        if (Version::compare($dbVersion, $minVersion, '<')) {
            $io->error(I18n::msg('sql_database_required_version', $dbType, $dbVersion, Setup::MIN_MYSQL_VERSION, Setup::MIN_MARIADB_VERSION));
            return Command::FAILURE;
        }

        // merge new defaults from the shipped default.config.yml into the user's config.yml
        $configPath = Path::coreData('config.yml');
        File::putConfig($configPath, array_merge(
            File::getConfig(Path::core('setup/default.config.yml')),
            File::getConfig($configPath),
        ));

        // align registered addons with composer state: drop config of orphaned addons, register new ones
        AddonManager::synchronizeWithFileSystem();

        if (!$this->convergeSchema($io)) {
            return Command::FAILURE;
        }

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

        $skipped = [];

        if (!$this->convergePackage($io, Migrator::CORE, static function (): void {
            require Path::core('setup/install.php');
        }, $skipped)) {
            return false;
        }

        foreach (AddonManager::getInstalledAddonOrder() as $addonName) {
            $addon = Addon::require($addonName);
            $manager = AddonManager::factory($addon);

            $converge = static function () use ($manager): void {
                if (!$manager->install()) {
                    throw new UserMessageException($manager->getMessage());
                }
            };

            if (!$this->convergePackage($io, $addonName, $converge, $skipped, !$addon->isActivated())) {
                return false;
            }
        }

        if (!$this->convergePackage($io, Migrator::PROJECT, static function (): void {
            Core::getProject()->install();
        }, $skipped)) {
            return false;
        }

        if ([] !== $skipped) {
            $io->warning(array_merge(
                ['Deactivated addons whose schema could not be updated. Their tables stay behind until the problem is fixed:'],
                array_map(static fn (string $addon): string => '  ' . $addon, $skipped),
            ));
        }

        return true;
    }

    /**
     * @param callable(): void $converge
     * @param list<string> $skipped
     * @param bool $tolerateFailure Report a failure instead of aborting (used for deactivated addons)
     * @return bool `false` if the package failed and the migration has to be aborted
     */
    private function convergePackage(SymfonyStyle $io, string $package, callable $converge, array &$skipped, bool $tolerateFailure = false): bool
    {
        $io->write(sprintf('  %s ... ', $package));

        try {
            $converge();
        } catch (UserMessageException $e) {
            return $this->handleConvergeFailure($io, $package, $this->decodeMessage($e->getMessage()), $skipped, $tolerateFailure);
        } catch (Throwable $e) {
            if (!$tolerateFailure) {
                $io->writeln('<error>FAIL</error>');
                throw $e;
            }

            return $this->handleConvergeFailure($io, $package, $e->getMessage(), $skipped, true);
        }

        $io->writeln('<info>OK</info>');

        return true;
    }

    /** @param list<string> $skipped */
    private function handleConvergeFailure(SymfonyStyle $io, string $package, string $message, array &$skipped, bool $tolerateFailure): bool
    {
        if ($tolerateFailure) {
            $io->writeln('<comment>SKIPPED</comment> ' . $message);
            $skipped[] = $package;

            return true;
        }

        $io->writeln('<error>FAIL</error> ' . $message);
        $io->error('Migration aborted.');

        return false;
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
