<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'migrate', description: 'Runs install scripts of core and addons to ensure schema is up to date')]
class MigrateCommand extends AbstractCommand implements StandaloneInterface
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

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

        $packages = array_merge(['core'], Core::getPackageOrder());

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

        $io->success('Migration completed.');
        return Command::SUCCESS;
    }
}
