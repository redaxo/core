<?php

namespace Redaxo\Core\Database;

/**
 * Class to represent sql indexes.
 */
final class Index
{
    public const string INDEX = 'INDEX';
    public const string UNIQUE = 'UNIQUE';
    public const string FULLTEXT = 'FULLTEXT';

    private bool $modified = false;

    /**
     * @param list<string> $columns
     * @param self::INDEX|self::UNIQUE|self::FULLTEXT $type
     */
    public function __construct(
        private string $name,
        private array $columns,
        private string $type = self::INDEX,
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

    /**
     * @param self::INDEX|self::UNIQUE|self::FULLTEXT $type
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this->setModified(true);
    }

    /** @return self::INDEX|self::UNIQUE|self::FULLTEXT */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param list<string> $columns
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this->setModified(true);
    }

    /** @return list<string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function equals(self $index): bool
    {
        return
            $this->name === $index->name
            && $this->type === $index->type
            && $this->columns === $index->columns;
    }
}
