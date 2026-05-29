<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Cache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'cache:clear', description: 'Clears the redaxo core cache')]
class CacheClearCommand extends AbstractCommand implements AvailableInSetupInterface
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $successMsg = Cache::delete();
        $io = $this->getStyle($input, $output);

        $io->success($this->decodeMessage($successMsg));
        return Command::SUCCESS;
    }
}
