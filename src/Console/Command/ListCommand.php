<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Core;
use Symfony\Component\Console\Command\ListCommand as SymfonyListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
final class ListCommand extends SymfonyListCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = parent::execute($input, $output);

        if (Core::isSetup()) {
            $io = new SymfonyStyle($input, $output);
            $outputFormatter = new OutputFormatterStyle('cyan');
            $io->getFormatter()->setStyle('info', $outputFormatter);

            $io->text('<info>These commands are available during the setup. After setup completed more commands will be available.</info>');
        }

        return $exitCode;
    }
}
