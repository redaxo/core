<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Backend\Style;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'assets:compile-styles', description: 'Converts Backend SCSS files to CSS')]
final class AssetsCompileStylesCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Backend style scss compiler');

        Style::compile();

        $io->success('Styles successfully compiled');

        return Command::SUCCESS;
    }
}
