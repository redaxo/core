<?php

namespace Redaxo\Core\Log;

use Stringable;

final readonly class LogEntry implements Stringable
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:sP';

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
     */
    public static function createFromString(string $string): self
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
     * @return int Unix timestamp
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Returns the log data.
     *
     * @return list<string>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        $data = array_map(static function ($part) {
            return trim(addcslashes($part, "|\n\\"));
        }, $this->data);
        $data = implode(' | ', $data);
        $data = str_replace("\r", '', $data);
        return date(self::DATE_FORMAT, $this->timestamp) . ' | ' . $data;
    }
}
