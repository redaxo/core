<?php

namespace Redaxo\Core\Database;

/**
 * Class to represent sql foreign keys.
 */
final class ForeignKey
{
    public const string RESTRICT = 'RESTRICT';
    public const string NO_ACTION = 'NO ACTION';
    public const string CASCADE = 'CASCADE';
    public const string SET_NULL = 'SET NULL';

    private bool $modified = false;

    /**
     * @param array<string, string> $columns Mapping of locale column to column in foreign table
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onUpdate
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onDelete
     */
    public function __construct(
        private string $name,
        private string $table,
        private array $columns,
        private string $onUpdate = self::RESTRICT,
        private string $onDelete = self::RESTRICT,
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

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this->setModified(true);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param array<string, string> $columns Mapping of locale column to column in foreign table
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this->setModified(true);
    }

    /** @return array<string, string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onUpdate
     */
    public function setOnUpdate(string $onUpdate): self
    {
        $this->onUpdate = $onUpdate;

        return $this->setModified(true);
    }

    /** @return self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL */
    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    /**
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onDelete
     */
    public function setOnDelete(string $onDelete): self
    {
        $this->onDelete = $onDelete;

        return $this->setModified(true);
    }

    /** @return self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL */
    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function equals(self $index): bool
    {
        return
            $this->name === $index->name
            && $this->table === $index->table
            && $this->columns === $index->columns
            && $this->onUpdate === $index->onUpdate
            && $this->onDelete === $index->onDelete;
    }
}
