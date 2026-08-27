<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Exception\InvalidArgumentException;

use function min;
use function sprintf;

/**
 * Class to represent sql columns.
 */
final class Column
{
    /**
     * Default display width of the integer types, signed and unsigned.
     *
     * @var array<string, array{int, int}>
     */
    private const array INT_DISPLAY_WIDTHS = [
        'tinyint' => [4, 3],
        'smallint' => [6, 5],
        'mediumint' => [9, 8],
        'int' => [11, 10],
        'bigint' => [20, 20],
    ];

    private bool $modified = false;

    public function __construct(
        private string $name,
        private string $type,
        private bool $nullable = false,
        private ?string $default = null,
        private ?string $extra = null,
        private ?string $comment = null,
    ) {}

    public static function tinyint(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null, bool $autoIncrement = false, ?string $comment = null): self
    {
        return self::integer('tinyint', $name, $unsigned, $nullable, $default, $autoIncrement, $comment);
    }

    public static function smallint(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null, bool $autoIncrement = false, ?string $comment = null): self
    {
        return self::integer('smallint', $name, $unsigned, $nullable, $default, $autoIncrement, $comment);
    }

    public static function mediumint(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null, bool $autoIncrement = false, ?string $comment = null): self
    {
        return self::integer('mediumint', $name, $unsigned, $nullable, $default, $autoIncrement, $comment);
    }

    public static function int(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null, bool $autoIncrement = false, ?string $comment = null): self
    {
        return self::integer('int', $name, $unsigned, $nullable, $default, $autoIncrement, $comment);
    }

    public static function bigint(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null, bool $autoIncrement = false, ?string $comment = null): self
    {
        return self::integer('bigint', $name, $unsigned, $nullable, $default, $autoIncrement, $comment);
    }

    /** A `tinyint(1)` column, which is how MySQL and MariaDB store booleans. */
    public static function bool(string $name, bool $nullable = false, ?bool $default = null, ?string $comment = null): self
    {
        return new self($name, 'tinyint(1)', $nullable, null === $default ? null : ($default ? '1' : '0'), null, $comment);
    }

    /** @param int<1, 65535> $length Maximum number of characters */
    public static function varchar(string $name, int $length, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        self::assertRange('length of a varchar column', $length, 1, 65535);

        return new self($name, 'varchar(' . $length . ')', $nullable, $default, null, $comment);
    }

    public static function text(string $name, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'text', $nullable, $default, null, $comment);
    }

    public static function mediumtext(string $name, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'mediumtext', $nullable, $default, null, $comment);
    }

    public static function longtext(string $name, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'longtext', $nullable, $default, null, $comment);
    }

    /**
     * @param int<1, 65> $precision Total number of digits
     * @param int<0, 30> $scale Number of digits after the decimal point, at most $precision
     */
    public static function decimal(string $name, int $precision, int $scale, bool $unsigned = false, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        self::assertRange('precision of a decimal column', $precision, 1, 65);
        self::assertRange('scale of a decimal column', $scale, 0, min(30, $precision));

        $type = 'decimal(' . $precision . ',' . $scale . ')' . ($unsigned ? ' unsigned' : '');

        return new self($name, $type, $nullable, $default, null, $comment);
    }

    /** @param int<0, 6>|null $precision Fractional seconds precision, whole seconds when omitted */
    public static function datetime(string $name, ?int $precision = null, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'datetime' . self::fractionalSeconds($precision), $nullable, $default, null, $comment);
    }

    public static function date(string $name, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'date', $nullable, $default, null, $comment);
    }

    /** @param int<0, 6>|null $precision Fractional seconds precision, whole seconds when omitted */
    public static function time(string $name, ?int $precision = null, bool $nullable = false, ?string $default = null, ?string $comment = null): self
    {
        return new self($name, 'time' . self::fractionalSeconds($precision), $nullable, $default, null, $comment);
    }

    /**
     * Builds the type for an integer column, including its display width.
     *
     * @internal
     */
    public static function intType(string $type, bool $unsigned): string
    {
        [$signedWidth, $unsignedWidth] = self::INT_DISPLAY_WIDTHS[$type];

        return $type . '(' . ($unsigned ? $unsignedWidth : $signedWidth) . ')' . ($unsigned ? ' unsigned' : '');
    }

    private static function integer(string $type, string $name, bool $unsigned, bool $nullable, ?int $default, bool $autoIncrement, ?string $comment): self
    {
        return new self(
            $name,
            self::intType($type, $unsigned),
            $nullable,
            null === $default ? null : (string) $default,
            $autoIncrement ? 'auto_increment' : null,
            $comment,
        );
    }

    private static function fractionalSeconds(?int $precision): string
    {
        if (null === $precision) {
            return '';
        }

        self::assertRange('fractional seconds precision', $precision, 0, 6);

        return '(' . $precision . ')';
    }

    private static function assertRange(string $subject, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf('The %s must be between %d and %d, got %d.', $subject, $min, $max, $value));
        }
    }

    public function setModified(bool $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this->setModified(true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this->setModified(true);
    }

    /** @return string The column type, including its size, e.g. int(10) or varchar(255) */
    public function getType(): string
    {
        return $this->type;
    }

    public function setNullable(bool $nullable): self
    {
        $this->nullable = $nullable;

        return $this->setModified(true);
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function setDefault(?string $default): self
    {
        $this->default = $default;

        return $this->setModified(true);
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setExtra(?string $extra): self
    {
        $this->extra = $extra;

        return $this->setModified(true);
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this->setModified(true);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function equals(self $column): bool
    {
        return
            $this->name === $column->name
            && $this->type === $column->type
            && $this->nullable === $column->nullable
            && self::normalizeDefault($this->type, $this->default) === self::normalizeDefault($column->type, $column->default)
            && self::normalizeExtra($this->extra) === self::normalizeExtra($column->extra)
            && $this->comment === $column->comment;
    }

    /**
     * Whether the default value is the current timestamp function instead of a literal value.
     *
     * @internal
     */
    public function hasCurrentTimestampDefault(): bool
    {
        return null !== $this->default && self::isCurrentTimestamp($this->type, $this->default);
    }

    /**
     * Normalizes the spelling of a default value to the form used by MySQL.
     *
     * @internal
     */
    public static function normalizeDefault(string $type, ?string $default): ?string
    {
        if (null === $default || !self::isCurrentTimestamp($type, $default)) {
            return $default;
        }

        return self::normalizeCurrentTimestamp($default);
    }

    /**
     * Normalizes an extra clause (like `on update current_timestamp()`) to the form used by MySQL.
     *
     * @internal
     */
    public static function normalizeExtra(?string $extra): ?string
    {
        if (null === $extra) {
            return null;
        }

        // Since MySQL 8.0.13 an expression default value is reported as `DEFAULT_GENERATED`, which is
        // not part of a column definition and would break the generated SQL.
        $extra = preg_replace('/^DEFAULT_GENERATED\s*/i', '', $extra) ?? $extra;

        return '' === $extra ? null : self::normalizeCurrentTimestamp($extra);
    }

    private static function isCurrentTimestamp(string $type, string $default): bool
    {
        return 1 === preg_match('/^(?:timestamp|datetime)(?:\(\d+\))?$/i', $type)
            && 1 === preg_match('/^current_timestamp(?:\(\s*\d*\s*\))?$/i', $default);
    }

    /** MySQL spells the function as `CURRENT_TIMESTAMP`, MariaDB as `current_timestamp()`. */
    private static function normalizeCurrentTimestamp(string $value): string
    {
        return preg_replace_callback(
            '/current_timestamp(?:\(\s*(\d*)\s*\))?/i',
            static fn (array $match) => 'CURRENT_TIMESTAMP' . (('' === ($match[1] ?? '')) ? '' : '(' . $match[1] . ')'),
            $value,
        ) ?? $value;
    }
}
