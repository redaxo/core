<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\DataList;
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

        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $sql->setQuery('CREATE TABLE `' . self::TABLE . '` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB');
        $sql->setQuery('INSERT INTO `' . self::TABLE . '` (`name`) VALUES ("aaa"), ("zzz"), ("<b>bold</b>")');
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['list'], $_REQUEST['sort'], $_REQUEST['sorttype']);

        Sql::factory()->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');

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

    /** @param array<string, string> $request */
    #[DataProvider('provideSortColumn')]
    public function testSortColumn(array $request, ?string $expected): void
    {
        $_REQUEST = $request + $_REQUEST;

        self::assertSame($expected, $this->createList()->getSortColumn());
    }

    /** @return iterable<string, array{array<string, string>, ?string}> */
    public static function provideSortColumn(): iterable
    {
        yield 'sortable column' => [['list' => 'mylist', 'sort' => 'name'], 'name'];
        yield 'column not sortable' => [['list' => 'mylist', 'sort' => 'id'], null];
        // error-based enumeration of other fields must not reach the query
        yield 'unknown column' => [['list' => 'mylist', 'sort' => 'password'], null];
        yield 'other list' => [['list' => 'otherlist', 'sort' => 'name'], null];
    }

    public function testRequestedSortIsApplied(): void
    {
        $_REQUEST['list'] = 'mylist';
        $_REQUEST['sort'] = 'name';
        $_REQUEST['sorttype'] = 'desc';

        $html = (string) $this->createList()->render();

        self::assertLessThan(strpos($html, 'aaa'), strpos($html, 'zzz'), 'rows must be sorted desc by name');
        self::assertStringContainsString('aria-sort="descending"', $html);
    }

    public function testSortOfColumnNotSortableKeepsDefaultOrder(): void
    {
        $_REQUEST['list'] = 'mylist';
        $_REQUEST['sort'] = 'id';
        $_REQUEST['sorttype'] = 'desc';

        $html = (string) $this->createList()->render();

        self::assertLessThan(strpos($html, 'zzz'), strpos($html, 'aaa'), 'rows must keep the default sort order');
    }

    public function testCellsAreEscapedAndLinked(): void
    {
        $list = $this->createList();
        $list->column('edit', null, static fn (array $row): string => 'edit ' . (int) $row['id'], link: static fn (array $row): ?string => 'zzz' === $row['name'] ? null : 'url-' . (int) $row['id']);

        $html = (string) $list->render();

        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
        self::assertStringContainsString('<a href="url-1" class="rex-u-link-expanded">edit 1</a>', $html);
        self::assertStringNotContainsString('url-2', $html);
        // the column without label joins the header of the previous one
        self::assertStringContainsString('<th colspan="2">', $html);
    }

    public function testEmptyListShowsMessage(): void
    {
        $list = new DataList('SELECT id FROM ' . self::TABLE . ' WHERE 0', 'mylist');
        $list->column('id', 'Id');
        $list->empty = 'nothing here';

        self::assertStringContainsString('<td colspan="1">nothing here</td>', (string) $list->render());
    }

    public function testInvalidDefaultSortIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @psalm-suppress InvalidArgument */
        new DataList('SELECT id FROM ' . self::TABLE, defaultSort: ['id' => 'asc; DROP TABLE x']); // @phpstan-ignore argument.type
    }

    private function createList(): DataList
    {
        $list = new DataList('SELECT id, name FROM ' . self::TABLE, 'mylist', DataList::DISABLE_PAGINATION, ['id' => 'asc']);
        $list->column('id', 'Id');
        $list->column('name', 'Name', sortable: true);

        return $list;
    }
}
