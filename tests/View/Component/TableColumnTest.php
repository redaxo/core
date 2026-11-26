<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Html;
use stdClass;

/** @internal */
final class TableColumnTest extends TestCase
{
    /** @param array<array-key, mixed> $row */
    #[DataProvider('provideForKeyReturnsTheEntry')]
    public function testForKeyReturnsTheEntry(mixed $expected, array $row): void
    {
        self::assertSame($expected, (TableColumn::forKey('Label', 'name')->cell)($row));
    }

    /** @return iterable<string, array{mixed, array<array-key, mixed>}> */
    public static function provideForKeyReturnsTheEntry(): iterable
    {
        $html = Html::raw('<b>foo</b>');
        $cell = new TableCell('foo', 2);

        yield 'string' => ['foo', ['name' => 'foo']];
        yield 'int' => [3, ['name' => 3]];
        yield 'float' => [1.5, ['name' => 1.5]];
        yield 'null' => [null, ['name' => null]];
        yield 'renderable' => [$html, ['name' => $html]];
        yield 'cell' => [$cell, ['name' => $cell]];
    }

    /** @param array<array-key, mixed> $row */
    #[DataProvider('provideForKeyRejectsTheRow')]
    public function testForKeyRejectsTheRow(string $expectedMessage, array $row): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        (TableColumn::forKey('Label', 'name')->cell)($row);
    }

    /** @return iterable<string, array{string, array<array-key, mixed>}> */
    public static function provideForKeyRejectsTheRow(): iterable
    {
        yield 'missing entry' => ['The row has no entry "name".', ['nmae' => 'foo']];
        yield 'no cell content' => ['The entry "name" of the row is no cell content, stdClass given.', ['name' => new stdClass()]];
        yield 'bool' => ['The entry "name" of the row is no cell content, bool given.', ['name' => true]];
    }
}
