<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Core;
use Redaxo\Core\Database\SchemaDumper;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'db:dump-schema', description: 'Dumps the schema of db tables as php code')]
final class DatabaseDumpSchemaCommand extends AbstractCommand
{
    public function __invoke(
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument('Database table', suggestedValues: static function (): array {
            return Sql::factory()->getTables(Core::getTablePrefix());
        })] string $table,
    ): int {
        $table = Table::get($table);

        if (!$table->exists()) {
            throw new InvalidArgumentException(sprintf('Table "%s" does not exist.', $table->getName()));
        }

        $generator = new SchemaDumper();

        $output->write($generator->dumpTable($table));

        $io = $io->getErrorStyle();
        $io->success('Generated schema for table "' . $table->getName() . '".');

        return Command::SUCCESS;
    }
}
