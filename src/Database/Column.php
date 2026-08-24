<?php

namespace Redaxo\Core\Database;

/**
 * Class to represent sql columns.
 */
final class Column
{
    private bool $modified = false;

    public function __construct(
        private string $name,
        private string $type,
        private bool $nullable = false,
        private ?string $default = null,
        private ?string $extra = null,
        private ?string $comment = null,
    ) {}

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
