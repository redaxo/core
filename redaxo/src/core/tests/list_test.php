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
