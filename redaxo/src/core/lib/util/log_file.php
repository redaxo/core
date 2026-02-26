<?php

/**
 * Log file class.
 *
 * @author gharlan
 *
 * @implements Iterator<int, rex_log_entry>
 * @package redaxo\core
 */
class rex_log_file implements Iterator
{
    use rex_factory_trait;

    /** @var string */
    private $path;

    /** @var resource */
    private $file;

    /** @var resource|null */
    private $file2;

    /** @var bool */
    private $second = false;

    /** @var int|null */
    private $pos;

    /** @var int|null */
    private $key;

    /** @var string|null */
    private $currentLine;

    /** @var string */
    private $buffer;

    /** @var int */
    private $bufferPos;

    /**
     * @param string $path File path
     * @param int|null $maxFileSize Maximum file size
     * @deprecated since 5.17.0, use `rex_log_file::factory` instead
     */
    public function __construct($path, $maxFileSize = null)
    {
        $this->path = $path;
        if (!is_file($path)) {
            rex_file::put($path, '');
        }
        if ($maxFileSize && filesize($path) > $maxFileSize) {
            rename($path, $path . '.2');
        }
        $this->file = fopen($path, 'a+');
    }

    public static function factory(string $path, ?int $maxFileSize = null): static
    {
        $class = static::getFactoryClass();
        return new $class($path, $maxFileSize); // @phpstan-ignore method.deprecated
    }

    /**
     * Adds a log entry.
     *
     * @param list<string|int> $data Log data
     * @return void
     */
    public function add(array $data)
    {
        fseek($this->file, 0, SEEK_END);
        fwrite($this->file, new rex_log_entry(time(), $data) . "\n");
    }

    /** @return rex_log_entry */
    #[ReturnTypeWillChange]
    public function current()
    {
        if (null === $this->currentLine) {
            throw new rex_exception('current() can not be used before calling rewind()/next() or after last line');
        }

        return rex_log_entry::createFromString($this->currentLine);
    }

    /** Reads the log file backwards line by line (each call reads one line). */
    #[ReturnTypeWillChange]
    public function next()
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

    /** @return int|null */
    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->key;
    }

    #[ReturnTypeWillChange]
    public function valid()
    {
        return !empty($this->currentLine);
    }

    #[ReturnTypeWillChange]
    public function rewind()
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
     *
     * @return bool
     */
    public static function delete($path)
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::delete($path);
        }

        return rex_file::delete($path) && rex_file::delete($path . '.2');
    }
}

/**
 * Log entry class.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_log_entry
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:sP';

    private int $timestamp;

    /** @var list<string> */
    private array $data;

    /** @param list<string|int> $data Log data */
    public function __construct(int $timestamp, array $data)
    {
        $this->timestamp = $timestamp;
        $this->data = array_map('strval', $data);
    }

    /**
     * Creates a log entry from string.
     *
     * @param string $string Log line
     *
     * @return rex_log_entry
     */
    public static function createFromString($string)
    {
        $data = [];
        foreach (explode(' |', $string) as $part) {
            $data[] = trim(stripcslashes($part));
        }

        $timestamp = strtotime(array_shift($data));

        return new self($timestamp, $data);
    }

    /**
     * Returns the timestamp.
     *
     * @param string|null $format Deprecated since 5.13.0, use `rex_formatter::intl*` instead. Format for {@link rex_formatter::strftime}
     *
     * @return int|string Unix timestamp or formatted string if $format is given
     */
    public function getTimestamp($format = null)
    {
        if (null === $format) {
            return $this->timestamp;
        }

        /** @psalm-suppress DeprecatedMethod */
        return rex_formatter::strftime($this->timestamp, $format); /** @phpstan-ignore-line */
    }

    /**
     * Returns the log data.
     *
     * @return list<string>
     */
    public function getData()
    {
        return $this->data;
    }

    /** @return string */
    public function __toString()
    {
        $data = array_map(static function ($part) {
            return trim(addcslashes($part, "|\n\\"));
        }, $this->data);
        $data = implode(' | ', $data);
        $data = str_replace("\r", '', $data);
        return date(self::DATE_FORMAT, $this->timestamp) . ' | ' . $data;
    }
}
