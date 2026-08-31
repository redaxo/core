<?php

namespace Redaxo\Core\Tests\Migration;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Migration\MigrationFile;
use Redaxo\Core\Migration\Migrator;

use function array_map;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class MigratorTest extends TestCase
{
    private const string ID_PREFIX = '0000-00-00-000000-migrator_test_';

    /** @var list<array{package: string, id: string, executed: string}> */
    private array $ledger = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The tests operate on the real ledger and on the core package, so back it up and restore it afterwards
        // instead of leaving real migrations marked as executed (or dropped) behind.
        $sql = Sql::factory();
        $this->ledger = array_map(
            static fn (array $row): array => [
                'package' => (string) $row['package'],
                'id' => (string) $row['id'],
                'executed' => (string) $row['executed'],
            ],
            $sql->getArray('SELECT * FROM ' . $sql->escapeIdentifier(Core::getTable('migration'))),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob(self::migrationPath('*')) ?: [] as $path) {
            File::delete($path);
        }

        $sql = Sql::factory();
        $table = $sql->escapeIdentifier(Core::getTable('migration'));

        $sql->setQuery('DELETE FROM ' . $table);
        foreach ($this->ledger as $row) {
            $sql->setTable(Core::getTable('migration'))->setValues($row)->insert();
        }

        $sql->setQuery('DROP TABLE IF EXISTS `rex_migrator_test_table`');
    }

    public function testMigrationsAreOrderedById(): void
    {
        $this->writeMigration('b');
        $this->writeMigration('a');

        self::assertSame(
            [self::ID_PREFIX . 'a', self::ID_PREFIX . 'b'],
            $this->testMigrationIds(Migrator::getMigrations(Migrator::CORE)),
        );
    }

    public function testPendingSkipsExecutedMigrations(): void
    {
        $this->writeMigration('a');
        $this->writeMigration('b');

        Migrator::markExecuted(new MigrationFile(Migrator::CORE, self::ID_PREFIX . 'a', self::migrationPath('a')));

        self::assertSame([self::ID_PREFIX . 'b'], $this->testMigrationIds(Migrator::getPending(Migrator::CORE)));
    }

    public function testRunExecutesTheMigrationAndRecordsIt(): void
    {
        $this->writeMigration('a', "Sql::factory()->setQuery('CREATE TABLE rex_migrator_test_table (id int(10) unsigned NOT NULL) ENGINE=InnoDB');");

        $migration = $this->pendingTestMigration();
        Migrator::run($migration);

        self::assertContains('rex_migrator_test_table', Sql::factory()->getTables());
        self::assertSame([], $this->testMigrationIds(Migrator::getPending(Migrator::CORE)));
    }

    public function testRunRejectsFileNotReturningAMigration(): void
    {
        $this->writeMigrationFile('a', "<?php\n\nreturn 'not a migration';\n");

        $this->expectException(RuntimeException::class);

        Migrator::run($this->pendingTestMigration());
    }

    public function testBaselineRecordsMigrationsWithoutRunningThem(): void
    {
        $this->writeMigration('a', "Sql::factory()->setQuery('CREATE TABLE rex_migrator_test_table (id int(10) unsigned NOT NULL) ENGINE=InnoDB');");

        Migrator::baseline(Migrator::CORE);

        self::assertSame([], $this->testMigrationIds(Migrator::getPending(Migrator::CORE)));
        self::assertNotContains('rex_migrator_test_table', Sql::factory()->getTables());
    }

    public function testForgetDropsTheLedgerEntries(): void
    {
        $this->writeMigration('a');
        Migrator::baseline(Migrator::CORE);
        self::assertSame([], $this->testMigrationIds(Migrator::getPending(Migrator::CORE)));

        Migrator::forget(Migrator::CORE);

        self::assertSame([self::ID_PREFIX . 'a'], $this->testMigrationIds(Migrator::getPending(Migrator::CORE)));
    }

    public function testForgetKeepsTheEntriesOfOtherPackages(): void
    {
        Migrator::markExecuted(new MigrationFile(Migrator::CORE, self::ID_PREFIX . 'a', self::migrationPath('a')));

        Migrator::forget('some-other-package');

        self::assertSame([self::ID_PREFIX . 'a'], $this->executedTestIds());
    }

    /** @return list<string> */
    private function executedTestIds(): array
    {
        $sql = Sql::factory();
        $ids = $sql->getArray(
            'SELECT `id` FROM ' . $sql->escapeIdentifier(Core::getTable('migration')) . ' WHERE `id` LIKE ?',
            [self::ID_PREFIX . '%'],
        );

        return array_map(static fn (array $row): string => (string) $row['id'], $ids);
    }

    private function pendingTestMigration(): MigrationFile
    {
        $pending = array_values(array_filter(
            Migrator::getPending(Migrator::CORE),
            static fn (MigrationFile $migration): bool => str_starts_with($migration->id, self::ID_PREFIX),
        ));

        self::assertCount(1, $pending);

        return $pending[0];
    }

    private function writeMigration(string $name, string $body = ''): void
    {
        $this->writeMigrationFile($name, <<<PHP
            <?php

            use Redaxo\\Core\\Database\\Sql;
            use Redaxo\\Core\\Migration\\Migration;

            return new class extends Migration {
                public function up(): void
                {
                    {$body}
                }
            };

            PHP);
    }

    private function writeMigrationFile(string $name, string $content): void
    {
        File::put(self::migrationPath($name), $content);
    }

    /** @return non-empty-string */
    private static function migrationPath(string $name): string
    {
        return Migrator::getDirectory(Migrator::CORE) . DIRECTORY_SEPARATOR . self::ID_PREFIX . $name . '.php';
    }

    /**
     * @param list<MigrationFile> $migrations
     * @return list<string>
     */
    private function testMigrationIds(array $migrations): array
    {
        $ids = array_map(static fn (MigrationFile $migration): string => $migration->id, $migrations);

        return array_values(array_filter($ids, static fn (string $id): bool => str_starts_with($id, self::ID_PREFIX)));
    }
}
