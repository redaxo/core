<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

/**
 * @internal
 */
class MigrateCommand extends AbstractCommand implements StandaloneInterface
{
    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Runs install scripts of core and addons to ensure schema is up to date');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

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
