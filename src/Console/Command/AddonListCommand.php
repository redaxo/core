<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * @internal
 */
#[AsCommand(name: 'addon:list', description: 'List available addons')]
final class AddonListCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option('filter list', shortcut: 's')] ?string $search = null,
        #[Option('search for exactly this addon', shortcut: 'p')] ?string $addon = null,
        #[Option('only list installed addons', shortcut: 'i')] bool $installedOnly = false,
        #[Option('only list active addons', shortcut: 'a')] bool $activatedOnly = false,
        #[Option('if no addon matches your filter the command exits with error-code 1, otherwise with 0')] bool $errorWhenEmpty = false,
        #[Option('output table as json')] bool $json = false,
    ): int {
        // the package manager don't know new packages in the addon folder
        // so we need to make them available
        AddonManager::synchronizeWithFileSystem();

        $packages = Addon::getRegisteredAddons();

        $rows = [];
        foreach ($packages as $package) {
            $rowdata = [
                'addon-id' => $package->name,
                'author' => $package->getAuthor(),
                'version' => $package->getVersion(),
                'installed' => $package->isInstalled(),
                'activated' => $package->isActivated(),
                'license' => $package->getLicense(),
            ];

            if (!$json) {
                $rowdata['installed'] = $rowdata['installed'] ? 'yes' : 'no';
                $rowdata['activated'] = $rowdata['activated'] ? 'yes' : 'no';
            }

            if (null !== $addon && $addon !== $package->name) {
                continue;
            }

            if (null !== $search && false === stripos($package->name, $search)) {
                continue;
            }

            if ($installedOnly && !$package->isInstalled()) {
                continue;
            }

            if ($activatedOnly && !$package->isActivated()) {
                continue;
            }

            $rows[] = $rowdata;
        }

        if ($json) {
            $io->writeln(json_encode($rows));
            return $errorWhenEmpty && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
        }

        $io->table(['addon-id', 'author', 'version', 'installed', 'activated', 'license'], $rows);
        return $errorWhenEmpty && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
    }
}
