<?php

namespace Redaxo\Core\Database;

/**
 * Class to represent sql indexes.
 */
final readonly class Index
{
    public const string INDEX = 'INDEX';
    public const string UNIQUE = 'UNIQUE';
    public const string FULLTEXT = 'FULLTEXT';

    /**
     * @param list<string> $columns
     * @param self::INDEX|self::UNIQUE|self::FULLTEXT $type
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $type = self::INDEX,
    ) {}

    public function withName(string $name): self
    {
        return new self($name, $this->columns, $this->type);
    }

    public function equals(self $index): bool
    {
        return
            $this->name === $index->name
            && $this->type === $index->type
            && $this->columns === $index->columns;
    }
}
