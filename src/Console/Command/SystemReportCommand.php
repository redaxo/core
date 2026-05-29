<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\SystemReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function is_bool;
use function sprintf;

use const STR_PAD_LEFT;

/**
 * @internal
 */
#[AsCommand(name: 'system:report', description: 'Shows the system report')]
class SystemReportCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format ("cli", "markdown")', 'cli', ['cli', 'markdown'])
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formats = ['cli', 'markdown'];

        $format = $input->getOption('format');

        if (!in_array($format, $formats, true)) {
            throw new InvalidOptionException(sprintf('Invalid value "%s" for --format option, allowed values: %s', $format, implode(', ', $formats)));
        }

        $report = SystemReport::factory();

        if ('markdown' === $format) {
            $output->writeln($report->asMarkdown());

            return Command::SUCCESS;
        }

        $io = $this->getStyle($input, $output);

        $io->title('System report');

        $tables = [];
        $maxLabelLength = 0;

        foreach ($report->get() as $groupLabel => $group) {
            $rows = [];

            foreach ($group as $label => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'yes' : 'no';
                }

                $rows[] = [$label, $value];
                $maxLabelLength = max($maxLabelLength, mb_strlen($label));
            }

            $tables[] = $table = new Table($io);
            $table->setHeaders([$groupLabel, '']);
            $table->setRows($rows);
        }

        $style = new TableStyle();

        $leftColumnStyle = clone $style;
        $leftColumnStyle->setPadType(STR_PAD_LEFT);

        foreach ($tables as $table) {
            $table->setColumnWidths([$maxLabelLength, 30]);

            $table->setStyle($style);
            $table->setColumnStyle(0, $leftColumnStyle);

            $table->render();
            $io->newLine();
        }

        return Command::SUCCESS;
    }
}
