<?php

namespace Redaxo\Core\Tests\Database;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

use function sprintf;

/**
 * Temporary probe: does InnoDB drop the index it generated for a foreign key once another index
 * covers the constraint? Green means it does, red means the redundant single column index lingers.
 *
 * @internal
 */
final class ForeignKeyIndexProbeTest extends TestCase
{
    private const string PARENT = 'rex_fk_probe_parent';
    private const string CHILD = 'rex_fk_probe_child';

    #[Override]
    protected function tearDown(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::CHILD . '`');
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::PARENT . '`');

        Table::clearInstancePool();
    }

    public function testGeneratedForeignKeyIndexIsDroppedWhenCovered(): void
    {
        Table::get(self::PARENT)
            ->ensurePrimaryIdColumn()
            ->ensure();

        Table::get(self::CHILD)
            ->ensurePrimaryIdColumn()
            ->ensureForeignIdColumn('parent_id', self::PARENT, nullable: true)
            ->ensureColumn(Column::int('priority', unsigned: true))
            ->ensure();

        $generated = self::CHILD . '_parent_id';
        $afterCreate = $this->indexes();

        self::assertContains($generated, $afterCreate, 'no index was generated for the foreign key');

        Table::clearInstancePool();
        Table::get(self::CHILD)
            ->ensureIndex(new Index('parent_priority', ['parent_id', 'priority']))
            ->ensure();

        $afterComposite = $this->indexes();

        self::assertNotContains($generated, $afterComposite, sprintf(
            'version %s keeps the generated index; after create: [%s], after composite: [%s]',
            $this->version(),
            implode(', ', $afterCreate),
            implode(', ', $afterComposite),
        ));
    }

    /** @return list<string> */
    private function indexes(): array
    {
        $names = [];
        foreach (Sql::factory()->getArray('SHOW INDEXES FROM `' . self::CHILD . '`') as $row) {
            $names[] = (string) $row['Key_name'];
        }

        return array_values(array_unique($names));
    }

    private function version(): string
    {
        return (string) Sql::factory()->getArray('SELECT VERSION() AS v')[0]['v'];
    }
}
