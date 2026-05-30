<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\SystemReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function in_array;
use function is_bool;
use function sprintf;

use const STR_PAD_LEFT;

/**
 * @internal
 */
#[AsCommand(name: 'system:report', description: 'Shows the system report')]
final class SystemReportCommand extends AbstractCommand
{
    private const array FORMATS = ['cli', 'markdown'];

    public function __invoke(
        OutputInterface $output,
        SymfonyStyle $io,
        #[Option('Output format ("cli", "markdown")', shortcut: 'f', suggestedValues: self::FORMATS)] string $format = 'cli',
    ): int {
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidOptionException(sprintf('Invalid value "%s" for --format option, allowed values: %s', $format, implode(', ', self::FORMATS)));
        }

        $report = SystemReport::factory();

        if ('markdown' === $format) {
            $output->writeln($report->asMarkdown());

            return Command::SUCCESS;
        }

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
