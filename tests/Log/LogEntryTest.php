<?php

namespace Redaxo\Core\Tests\Log;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Log\LogEntry;

/** @internal */
final class LogEntryTest extends TestCase
{
    public function testConstruct(): void
    {
        $time = time();
        $data = ['test1', 'test2'];
        $entry = new LogEntry($time, $data);

        self::assertSame($time, $entry->getTimestamp());
        self::assertSame($data, $entry->getData());
    }

    public function testCreateFromString(): void
    {
        $time = time();
        $entry = LogEntry::createFromString(date(LogEntry::DATE_FORMAT, $time) . ' | test1 |  |  test2\nt \| test3 |');

        self::assertSame($time, $entry->getTimestamp());
        self::assertSame(['test1', '', "test2\nt | test3", ''], $entry->getData());
    }

    #[Depends('testConstruct')]
    public function testGetTimestamp(): void
    {
        $time = time();
        $entry = new LogEntry($time, []);

        self::assertSame($time, $entry->getTimestamp());
    }

    #[Depends('testConstruct')]
    public function testToString(): void
    {
        $time = time();
        $entry = new LogEntry($time, ['test1', ' ', " test2\nt | test3\r\ntest4 "]);

        self::assertSame(date(LogEntry::DATE_FORMAT, $time) . ' | test1 |  | test2\nt \| test3\ntest4', $entry->__toString());
    }
}
