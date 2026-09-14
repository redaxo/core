<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
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
        #[Option('if no addon matches your filter the command exits with error-code 1, otherwise with 0')] bool $errorWhenEmpty = false,
        #[Option('output table as json')] bool $json = false,
    ): int {
        $rows = [];
        foreach (Addon::getAll() as $package) {
            if (null !== $addon && $addon !== $package->name) {
                continue;
            }

            if (null !== $search && false === stripos($package->name, $search)) {
                continue;
            }

            $rows[] = [
                'addon-id' => $package->name,
                'author' => $package->getAuthor(),
                'version' => $package->getVersion(),
                'license' => $package->getLicense(),
            ];
        }

        if ($json) {
            $io->writeln(json_encode($rows));
            return $errorWhenEmpty && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
        }

        $io->table(['addon-id', 'author', 'version', 'license'], $rows);
        return $errorWhenEmpty && 0 === count($rows) ? Command::FAILURE : Command::SUCCESS;
    }
}
