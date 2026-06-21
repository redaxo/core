<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MetaInfo\MetaSync;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_map;
use function array_merge;
use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'migrate', description: 'Runs install scripts of core and addons to ensure schema is up to date')]
final class MigrateCommand extends AbstractCommand implements StandaloneInterface
{
    public function __invoke(InputInterface $input, SymfonyStyle $io): int
    {
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

        $packages = array_merge(['core'], AddonManager::getAddonOrder());

        $io->section('Migrating');

        foreach ($packages as $package) {
            $io->write(sprintf('  %s ... ', $package));

            try {
                if ('core' === $package) {
                    require Path::core('setup/install.php');
                } else {
                    $manager = AddonManager::factory(Addon::require($package));
                    if (!$manager->install()) {
                        $io->writeln('<error>FAIL</error> ' . $this->decodeMessage($manager->getMessage()));
                        $io->error('Migration aborted.');
                        return Command::FAILURE;
                    }
                }
            } catch (UserMessageException $e) {
                $io->writeln('<error>FAIL</error> ' . $this->decodeMessage($e->getMessage()));
                $io->error('Migration aborted.');
                return Command::FAILURE;
            } catch (Throwable $e) {
                $io->writeln('<error>FAIL</error>');
                throw $e;
            }

            $io->writeln('<info>OK</info>');
        }

        $this->syncMetaInfo($input, $io);

        $io->success('Migration completed.');
        return Command::SUCCESS;
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
