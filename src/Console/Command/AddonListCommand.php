<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;

/**
 * @internal
 */
class AddonListCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('List available addons')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'filter list')
            ->addOption('addon', 'p', InputOption::VALUE_REQUIRED, 'search for exactly this addon-id')
            ->addOption('installed-only', 'i', InputOption::VALUE_NONE, 'only list installed addons')
            ->addOption('activated-only', 'a', InputOption::VALUE_NONE, 'only list active addons')
            ->addOption('error-when-empty', null, InputOption::VALUE_NONE, 'if no addon matches your filter the command exits with error-code 1, otherwise with 0')
            ->addOption('json', null, InputOption::VALUE_NONE, 'output table as json')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        // the package manager don't know new packages in the addon folder
        // so we need to make them available
        AddonManager::synchronizeWithFileSystem();

        $search = $input->getOption('search');
        $packageId = $input->getOption('addon');

        $installedOnly = false !== $input->getOption('installed-only');
        $activatedOnly = false !== $input->getOption('activated-only');
        $jsonOutput = false !== $input->getOption('json');
        $usingExitCode = false !== $input->getOption('error-when-empty');

        $packages = Addon::getRegisteredAddons();

        $rows = [];
        foreach ($packages as $package) {
            $rowdata = [
                'addon-id' => $package->name,
                'author' => $package->getAuthor(),
                'version' => $package->getVersion(),
                'installed' => $package->isInstalled(),
                'activated' => $package->isAvailable(),
                'license' => $package->getLicense(),
            ];

            if (!$jsonOutput) {
                $rowdata['installed'] = $rowdata['installed'] ? 'yes' : 'no';
                $rowdata['activated'] = $rowdata['activated'] ? 'yes' : 'no';
            }

            if (null !== $packageId && $packageId !== $package->name) {
                continue;
            }

            if (null !== $search && false === stripos($package->name, $search)) {
                continue;
            }

            if ($installedOnly && !$package->isInstalled()) {
                continue;
            }

            if ($activatedOnly && !$package->isAvailable()) {
                continue;
            }

            $rows[] = $rowdata;
        }

        if ($jsonOutput) {
            $io->writeln(json_encode($rows));
            return $usingExitCode && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
        }

        $io->table(['addon-id', 'author', 'version', 'installed', 'activated', 'license'], $rows);
        return $usingExitCode && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
    }
}
