<?php

namespace Redaxo\Core\Tests\Log;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Log\LogEntry;
use Redaxo\Core\Log\LogFile;

/** @internal */
final class LogFileTest extends TestCase
{
    protected function tearDown(): void
    {
        Dir::delete($this->getPath());
    }

    private function getPath(string $file = ''): string
    {
        return Path::addonData('test', 'LogFileTest/' . $file);
    }

    public function testConstruct(): void
    {
        $path = $this->getPath('test1.log');
        LogFile::factory($path);
        self::assertStringEqualsFile($path, '');
    }

    public function testConstructWithMaxFileSize(): void
    {
        $path = $this->getPath('test2.log');
        $path2 = $path . '.2';

        LogFile::factory($path, 20);
        self::assertStringEqualsFile($path, '');
        self::assertFileDoesNotExist($path2);

        $content = str_repeat('abc', 5);
        File::put($path, $content);

        LogFile::factory($path, 20);
        self::assertFileDoesNotExist($path2);
        self::assertStringEqualsFile($path, $content);

        LogFile::factory($path, 10);
        self::assertStringEqualsFile($path2, $content);
        self::assertStringEqualsFile($path, '');
    }

    #[Depends('testConstruct')]
    public function testAdd(): void
    {
        $path = $this->getPath('test3.log');
        $log = LogFile::factory($path);
        $log->add(['test1a', 'test1b']);
        $log->add(['test2a', 'test2b', 'test2c']);

        $format = <<<'EOF'
            %i-%i-%iT%i:%i:%i%i:%i | test1a | test1b
            %i-%i-%iT%i:%i:%i%i:%i | test2a | test2b | test2c
            EOF;
        self::assertStringMatchesFormat($format, File::require($path));
    }

    #[Depends('testConstruct')]
    public function testIterator(): void
    {
        $path = $this->getPath('test4.log');
        $log = LogFile::factory($path);
        self::assertSame([], iterator_to_array($log));

        unset($log); // free handles to the underlying file
        File::put($path, <<<'EOF'
            2013-08-27 23:07:02 | test1a | test1b
            2013-08-27 23:09:43 | test2a | test2b
            EOF
        );
        $expected = [
            new LogEntry(mktime(23, 9, 43, 8, 27, 2013), ['test2a', 'test2b']),
            new LogEntry(mktime(23, 7, 2, 8, 27, 2013), ['test1a', 'test1b']),
        ];
        $log = LogFile::factory($path);
        self::assertEquals($expected, iterator_to_array($log));

        unset($log); // free handles to the underlying file
        File::put($path . '.2', <<<'EOF'

            2013-08-27 22:19:02 | test3

            2013-08-27 22:22:43 | test4

            EOF
        );
        $expected[] = new LogEntry(mktime(22, 22, 43, 8, 27, 2013), ['test4']);
        $expected[] = new LogEntry(mktime(22, 19, 2, 8, 27, 2013), ['test3']);
        $log = LogFile::factory($path);
        self::assertEquals($expected, iterator_to_array($log));
    }

    public function testDelete(): void
    {
        $path = $this->getPath('delete.log');
        $path2 = $path . '.2';
        File::put($path, '');
        File::put($path2, '');

        LogFile::delete($path);

        self::assertFileDoesNotExist($path);
        self::assertFileDoesNotExist($path2);
    }
}
