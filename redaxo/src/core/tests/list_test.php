<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_list_test extends TestCase
{
    public function testPrepareCountQuery(): void
    {
        $method = new ReflectionMethod(rex_list::class, 'prepareCountQuery');

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

        unset($_REQUEST['list'], $_REQUEST['sort']);
    }

    public function testGetSortColumnIgnoredForOtherList(): void
    {
        $list = $this->createListWithSortableColumn('mylist', 'name');

        $_REQUEST['list'] = 'otherlist';
        $_REQUEST['sort'] = 'name';

        self::assertNull($list->getSortColumn());
        self::assertSame('id', $list->getSortColumn('id'));

        unset($_REQUEST['list'], $_REQUEST['sort']);
    }

    public function testSortableColumnRegisteredAfterConstructionIsApplied(): void
    {
        $table = 'rex_tests_list';

        $sql = rex_sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . $table . '`');
        $sql->setQuery('CREATE TABLE `' . $table . '` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB');
        $sql->setQuery('INSERT INTO `' . $table . '` (`name`) VALUES ("aaa"), ("zzz")');

        $_REQUEST['list'] = 'sortlist';
        $_REQUEST['sort'] = 'name';
        $_REQUEST['sorttype'] = 'desc';

        try {
            // setColumnSortable() is called after the constructor already executed the query,
            // the requested sort order must still be applied (https://github.com/redaxo/core/issues/6585)
            $list = rex_list::factory('SELECT id, name FROM ' . $table, rex_list::DISABLE_PAGINATION, 'sortlist');
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
            unset($_REQUEST['list'], $_REQUEST['sort'], $_REQUEST['sorttype']);
            $sql->setQuery('DROP TABLE `' . $table . '`');
        }
    }

    public function testNonSortableSortParamFallsBackToDefaultOrder(): void
    {
        $table = 'rex_tests_list';

        $sql = rex_sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . $table . '`');
        $sql->setQuery('CREATE TABLE `' . $table . '` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB');
        $sql->setQuery('INSERT INTO `' . $table . '` (`name`) VALUES ("aaa"), ("zzz")');

        $_REQUEST['list'] = 'sortlist';
        $_REQUEST['sort'] = 'name';
        $_REQUEST['sorttype'] = 'desc';

        try {
            // "name" is never marked as sortable, so the requested sort must be ignored
            $list = rex_list::factory('SELECT id, name FROM ' . $table, rex_list::DISABLE_PAGINATION, 'sortlist', defaultSort: ['id' => 'asc']);

            $html = $list->get();

            $posZzz = strpos($html, 'zzz');
            $posAaa = strpos($html, 'aaa');
            self::assertNotFalse($posZzz);
            self::assertNotFalse($posAaa);
            self::assertLessThan($posZzz, $posAaa, 'rows must keep the default sort order');
        } finally {
            unset($_REQUEST['list'], $_REQUEST['sort'], $_REQUEST['sorttype']);
            $sql->setQuery('DROP TABLE `' . $table . '`');
        }
    }

    private function createListWithSortableColumn(string $name, string $sortableColumn): rex_list
    {
        $list = (new ReflectionClass(rex_list::class))->newInstanceWithoutConstructor();

        $nameProp = new ReflectionProperty(rex_list::class, 'name');
        $nameProp->setValue($list, $name);

        $optionsProp = new ReflectionProperty(rex_list::class, 'columnOptions');
        $optionsProp->setValue($list, []);

        $list->setColumnSortable($sortableColumn);

        return $list;
    }
}
