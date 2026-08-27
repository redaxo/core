<?php

namespace Redaxo\Core\Database;

/**
 * Class to represent sql foreign keys.
 */
final readonly class ForeignKey
{
    public const string RESTRICT = 'RESTRICT';
    public const string NO_ACTION = 'NO ACTION';
    public const string CASCADE = 'CASCADE';
    public const string SET_NULL = 'SET NULL';

    /**
     * @param array<string, string> $columns Mapping of locale column to column in foreign table
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onUpdate
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onDelete
     */
    public function __construct(
        public string $name,
        public string $table,
        public array $columns,
        public string $onUpdate = self::RESTRICT,
        public string $onDelete = self::RESTRICT,
    ) {}

    public function withName(string $name): self
    {
        return new self($name, $this->table, $this->columns, $this->onUpdate, $this->onDelete);
    }

    public function equals(self $foreignKey): bool
    {
        return
            $this->name === $foreignKey->name
            && $this->table === $foreignKey->table
            && $this->columns === $foreignKey->columns
            && $this->onUpdate === $foreignKey->onUpdate
            && $this->onDelete === $foreignKey->onDelete;
    }
}
