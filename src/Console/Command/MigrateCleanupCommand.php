<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\AddonManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Removes what `migrate` deliberately leaves alone because it is destructive: the data of addons that are no
 * longer installed via composer.
 *
 * Meant to run after a deployment switched to the new release — `migrate` runs while the old release is still
 * live, and that one may still use the removed addon.
 *
 * @internal
 */
#[AsCommand(name: 'migrate:cleanup', description: 'Removes the data of addons that are no longer installed via composer')]
final class MigrateCleanupCommand extends AbstractCommand implements StandaloneInterface
{
    public function __invoke(SymfonyStyle $io): int
    {
        $orphans = AddonManager::getOrphans();

        if ([] === $orphans) {
            $io->success('Nothing to clean up.');

            return Command::SUCCESS;
        }

        foreach ($orphans as $addon) {
            if (null !== $blocker = AddonManager::getOrphanRemovalBlocker($addon)) {
                $io->writeln(sprintf('  %s ... <comment>SKIPPED</comment> %s', $addon, $blocker));

                continue;
            }

            $removed = AddonManager::removeOrphan($addon);
            $io->writeln(sprintf('  %s ... <info>REMOVED</info> (%d tables, %d columns)', $addon, $removed['tables'], $removed['columns']));
        }

        $io->success('Cleanup completed.');

        return Command::SUCCESS;
    }
}
