<?php

namespace Redaxo\Core\Log;

use Iterator;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\File;

use function assert;
use function strlen;

use const SEEK_END;

/**
 * @implements Iterator<int, LogEntry>
 *
 * @psalm-consistent-constructor
 */
class LogFile implements Iterator
{
    use FactoryTrait;

    private readonly string $path;

    /** @var resource */
    private $file;

    /** @var resource|null */
    private $file2;

    private bool $second = false;
    private ?int $pos = null;
    private ?int $key = null;
    private ?string $currentLine = null;
    private string $buffer;
    private int $bufferPos;

    private function __construct(string $path, ?int $maxFileSize = null)
    {
        $this->path = $path;
        if (!is_file($path)) {
            File::put($path, '');
        }
        if ($maxFileSize && filesize($path) > $maxFileSize) {
            rename($path, $path . '.2');
        }
        $this->file = fopen($path, 'a+');
    }

    public static function factory(string $path, ?int $maxFileSize = null): static
    {
        $class = static::getFactoryClass();
        return new $class($path, $maxFileSize);
    }

    /**
     * Adds a log entry.
     *
     * @param list<string|int> $data Log data
     */
    public function add(array $data): void
    {
        fseek($this->file, 0, SEEK_END);
        fwrite($this->file, new LogEntry(time(), $data) . "\n");
    }

    public function current(): LogEntry
    {
        if (null === $this->currentLine) {
            throw new LogicException('current() can not be used before calling rewind()/next() or after last line');
        }

        return LogEntry::createFromString($this->currentLine);
    }

    /** Reads the log file backwards line by line (each call reads one line). */
    public function next(): void
    {
        $bufferSize = 500;

        if ($this->pos < 0) {
            // position is before file start -> look for next file
            $path2 = $this->path . '.2';
            if ($this->second || !$this->file2 && !is_file($path2)) {
                // already in file2 or file2 does not exist -> mark currentLine as invalid
                $this->currentLine = null;
                $this->key = null;
                return;
            }
            // switch to file2 and reset position
            if (!$this->file2) {
                $this->file2 = fopen($path2, 'r');
            }
            $this->second = true;
            $this->pos = null;
        }

        // get current file
        $file = $this->second ? $this->file2 : $this->file;
        assert(null !== $file);

        if (null === $this->pos) {
            // position is not set -> set start position to start of last buffer
            fseek($file, 0, SEEK_END);
            $this->pos = (int) (ftell($file) / $bufferSize) * $bufferSize;
        }

        $line = '';
        // while position is not before file start
        while ($this->pos >= 0) {
            if ($this->bufferPos < 0) {
                // read next buffer
                fseek($file, $this->pos);
                $this->buffer = fread($file, $bufferSize);
                $this->bufferPos = strlen($this->buffer) - 1;
            }
            // read buffer backwards char by char
            for (; $this->bufferPos >= 0; --$this->bufferPos) {
                $char = $this->buffer[$this->bufferPos];
                if ("\n" === $char) {
                    // line start reached -> prepare bufferPos/pos and jump outside of while-loop
                    --$this->bufferPos;
                    if ($this->bufferPos < 0) {
                        $this->pos -= $bufferSize;
                    }
                    break 2;
                }
                if ("\r" !== $char) {
                    // build line; \r is ignored
                    $line = $char . $line;
                }
            }
            $this->pos -= $bufferSize;
        }
        if (!$line = trim($line)) {
            // empty lines are skipped -> read next line
            $this->next();
            return;
        }
        // found a non-empty line
        $this->key = null === $this->key ? 0 : $this->key + 1;
        $this->currentLine = $line;
    }

    public function key(): ?int
    {
        return $this->key;
    }

    public function valid(): bool
    {
        return !empty($this->currentLine);
    }

    public function rewind(): void
    {
        $this->second = false;
        $this->pos = null;
        $this->key = -1;
        $this->bufferPos = -1;
        $this->next();
    }

    /**
     * Deletes a log file and its rotations.
     *
     * @param string $path File path
     */
    public static function delete($path): bool
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::delete($path);
        }

        return File::delete($path) && File::delete($path . '.2');
    }
}
