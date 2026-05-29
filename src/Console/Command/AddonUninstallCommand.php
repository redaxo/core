<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'addon:uninstall', description: 'Uninstalls the selected addon')]
class AddonUninstallCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the addon, e.g. "yform"', suggestedValues: static function (): array {
            return array_keys(Addon::getInstalledAddons());
        })] string $addon,
    ): int {
        // the package manager don't know new packages in the addon folder
        // so we need to make them available
        AddonManager::synchronizeWithFileSystem();

        $package = Addon::get($addon);
        if (!$package) {
            $io->error('Addon "' . $addon . '" doesn\'t exists!');
            return Command::FAILURE;
        }

        $manager = AddonManager::factory($package);
        $success = $manager->uninstall();
        $message = $this->decodeMessage($manager->getMessage());

        if ($success) {
            $io->success($message);
            return Command::SUCCESS;
        }

        $io->error($message);
        return Command::FAILURE;
    }
}
