<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Exception\InvalidArgumentException;

use function in_array;
use function min;
use function sprintf;
use function strtolower;

/**
 * Class to represent sql columns.
 */
final class Column
{
    private const array INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'];

    /** Type names accepted as a synonym by the server, which reports the canonical name instead. */
    private const array TYPE_ALIASES = [
        'integer' => 'int',
        'dec' => 'decimal',
        'numeric' => 'decimal',
        'fixed' => 'decimal',
        'real' => 'double',
    ];

    private bool $modified = false;

    public function __construct(
        private string $name,
        private string $type { set => self::normalizeType($value); },
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

    private static function integer(string $type, string $name, bool $unsigned, bool $nullable, ?int $default, bool $autoIncrement, ?string $comment): self
    {
        return new self(
            $name,
            $type . ($unsigned ? ' unsigned' : ''),
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

    /** @return string The normalized column type, e.g. `int unsigned` or `varchar(255)` */
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
     * Normalizes a column type to the spelling that column comparison is based on.
     *
     * The display width of integer types is dropped, because it carries no meaning and MySQL stopped
     * reporting it in 8.0.17 while MariaDB still does. `tinyint(1)` keeps it, as both engines report it
     * and it is the idiomatic boolean type, and so do columns with `zerofill`, where the width decides
     * how far a value is padded.
     *
     * @internal
     */
    public static function normalizeType(string $type): string
    {
        if (!preg_match('/^\s*(\w+)\s*(?:\(([^)]*)\))?\s*(.*?)\s*$/', $type, $match)) {
            return $type;
        }

        $name = strtolower($match[1]);
        $parameters = $match[2] ?? '';
        $attributes = strtolower(preg_replace('/\s+/', ' ', $match[3]) ?? $match[3]);

        if (in_array($name, ['bool', 'boolean'], true)) {
            $name = 'tinyint';
            $parameters = '1';
        }

        $name = self::TYPE_ALIASES[$name] ?? $name;

        // Only numeric parameters may be reformatted, the values of `enum` and `set` must stay untouched.
        if (preg_match('/^[\d\s,]*$/', $parameters)) {
            $parameters = preg_replace('/\s+/', '', $parameters) ?? $parameters;
        }

        if (
            in_array($name, self::INTEGER_TYPES, true)
            && !str_contains($attributes, 'zerofill')
            && !('tinyint' === $name && '1' === $parameters && '' === $attributes)
        ) {
            $parameters = '';
        }

        return $name
            . ('' === $parameters ? '' : '(' . $parameters . ')')
            . ('' === $attributes ? '' : ' ' . $attributes);
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

        if (0 === strcasecmp('auto_increment', $extra)) {
            return 'auto_increment';
        }

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
