<?php

/**
 * Class to represent sql indexes.
 *
 * @author gharlan
 *
 * @package redaxo\core\sql
 */
class rex_sql_index
{
    public const INDEX = 'INDEX';
    public const UNIQUE = 'UNIQUE';
    public const FULLTEXT = 'FULLTEXT';

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

    /**
     * @param bool $modified
     *
     * @return $this
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /** @return bool */
    public function isModified()
    {
        return $this->modified;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this->setModified(true);
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param self::INDEX|self::UNIQUE|self::FULLTEXT $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this->setModified(true);
    }

    /** @return self::INDEX|self::UNIQUE|self::FULLTEXT */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param list<string> $columns
     *
     * @return $this
     */
    public function setColumns(array $columns)
    {
        $this->columns = $columns;

        return $this->setModified(true);
    }

    /** @return list<string> */
    public function getColumns()
    {
        return $this->columns;
    }

    /** @return bool */
    public function equals(self $index)
    {
        return
            $this->name === $index->name
            && $this->type === $index->type
            && $this->columns === $index->columns;
    }
}
