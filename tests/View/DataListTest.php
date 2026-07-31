<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\View\DataList;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/** @internal */
final class DataListTest extends TestCase
{
    private const TABLE = 'rex_tests_list';

    protected function setUp(): void
    {
        // the console environment counts as backend, so DataList expects a current backend page
        Controller::setCurrentPage('tests');
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['list'], $_REQUEST['sort'], $_REQUEST['sorttype']);

        new ReflectionProperty(Controller::class, 'page')->setValue(null, null);
        new ReflectionProperty(Controller::class, 'pageParts')->setValue(null, []);
    }

    public function testPrepareCountQuery(): void
    {
        $method = new ReflectionMethod(DataList::class, 'prepareCountQuery');

        $query = 'SELECT *, IF(foo = 1, 0, (SELECT x FROM bar)) as qux FROM foo ORDER BY qux';
        $expected = 'SELECT COUNT(*) AS `rows` FROM (SELECT *, IF(foo = 1, 0, (SELECT x FROM bar)) as qux FROM foo ORDER BY qux) t';

        self::assertSame($expected, $method->invoke(null, $query));
    }

    public function testGetSortColumnOnlyAllowsSortableColumns(): void
    {
        $list = $this->createListWithSortableColumn('mylist', 'name');

        $_REQUEST['list'] = 'mylist';

        // sortable column is accepted
        $_REQUEST['sort'] = 'name';
        self::assertSame('name', $list->getSortColumn());

        // non-sortable / unselected column is rejected and falls back to default
        $_REQUEST['sort'] = 'password';
        self::assertNull($list->getSortColumn());
        self::assertSame('id', $list->getSortColumn('id'));

        // non-existent column (error-based enumeration attempt) is rejected
        $_REQUEST['sort'] = 'nonexistent_col';
        self::assertNull($list->getSortColumn());
    }

    public function testGetSortColumnIgnoredForOtherList(): void
    {
        $list = $this->createListWithSortableColumn('mylist', 'name');

        $_REQUEST['list'] = 'otherlist';
        $_REQUEST['sort'] = 'name';

        self::assertNull($list->getSortColumn());
        self::assertSame('id', $list->getSortColumn('id'));
    }

    public function testSortableColumnRegisteredAfterConstructionIsApplied(): void
    {
        $this->createTable();

        $_REQUEST['list'] = 'sortlist';
        $_REQUEST['sort'] = 'name';
        $_REQUEST['sorttype'] = 'desc';

        try {
            // setColumnSortable() is called after the constructor already executed the query,
            // the requested sort order must still be applied (https://github.com/redaxo/core/issues/6585)
            $list = DataList::factory('SELECT id, name FROM ' . self::TABLE, DataList::DISABLE_PAGINATION, 'sortlist');
            $list->setColumnSortable('name');

            $html = $list->get();

            $posZzz = strpos($html, 'zzz');
            $posAaa = strpos($html, 'aaa');
            self::assertNotFalse($posZzz);
            self::assertNotFalse($posAaa);
            self::assertLessThan($posAaa, $posZzz, 'rows must be sorted desc by name');
            self::assertSame(2, $list->getRows());
            self::assertSame(['id', 'name'], $list->getColumnNames());
        } finally {
            $this->dropTable();
        }
    }

    public function testNonSortableSortParamFallsBackToDefaultOrder(): void
    {
        $this->createTable();

        $_REQUEST['list'] = 'sortlist';
        $_REQUEST['sort'] = 'name';
        $_REQUEST['sorttype'] = 'desc';

        try {
            // "name" is never marked as sortable, so the requested sort must be ignored
            $list = DataList::factory('SELECT id, name FROM ' . self::TABLE, DataList::DISABLE_PAGINATION, 'sortlist', defaultSort: ['id' => 'asc']);

            $html = $list->get();

            $posZzz = strpos($html, 'zzz');
            $posAaa = strpos($html, 'aaa');
            self::assertNotFalse($posZzz);
            self::assertNotFalse($posAaa);
            self::assertLessThan($posZzz, $posAaa, 'rows must keep the default sort order');
        } finally {
            $this->dropTable();
        }
    }

    private function createTable(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $sql->setQuery('CREATE TABLE `' . self::TABLE . '` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB');
        $sql->setQuery('INSERT INTO `' . self::TABLE . '` (`name`) VALUES ("aaa"), ("zzz")');
    }

    private function dropTable(): void
    {
        Sql::factory()->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
    }

    private function createListWithSortableColumn(string $name, string $sortableColumn): DataList
    {
        $list = new ReflectionClass(DataList::class)->newInstanceWithoutConstructor();

        $nameProp = new ReflectionProperty(DataList::class, 'name');
        $nameProp->setValue($list, $name);

        $optionsProp = new ReflectionProperty(DataList::class, 'columnOptions');
        $optionsProp->setValue($list, []);

        $list->setColumnSortable($sortableColumn);

        return $list;
    }
}
