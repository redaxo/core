<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Cache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'cache:clear', description: 'Clears the redaxo core cache')]
final class CacheClearCommand extends AbstractCommand implements AvailableInSetupInterface
{
    public function __invoke(SymfonyStyle $io): int
    {
        $successMsg = Cache::delete();

        $io->success($this->decodeMessage($successMsg));
        return Command::SUCCESS;
    }
}
