<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Backend\Style;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'assets:compile-styles', description: 'Converts Backend SCSS files to CSS')]
class AssetsCompileStylesCommand extends AbstractCommand
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);
        $io->title('Backend style scss compiler');

        Style::compile();

        $io->success('Styles successfully compiled');

        return Command::SUCCESS;
    }
}
