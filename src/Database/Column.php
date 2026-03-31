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
            && $this->default === $column->default
            && $this->extra === $column->extra
            && $this->comment === $column->comment;
    }
}
