<?php

/**
 * Class to represent sql foreign keys.
 *
 * @author gharlan
 *
 * @package redaxo\core\sql
 */
class rex_sql_foreign_key
{
    public const RESTRICT = 'RESTRICT';
    public const NO_ACTION = 'NO ACTION';
    public const CASCADE = 'CASCADE';
    public const SET_NULL = 'SET NULL';

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
     * @param string $table
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;

        return $this->setModified(true);
    }

    /** @return string */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param array<string, string> $columns Mapping of locale column to column in foreign table
     *
     * @return $this
     */
    public function setColumns(array $columns)
    {
        $this->columns = $columns;

        return $this->setModified(true);
    }

    /** @return array<string, string> */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onUpdate
     *
     * @return $this
     */
    public function setOnUpdate($onUpdate)
    {
        $this->onUpdate = $onUpdate;

        return $this->setModified(true);
    }

    /** @return self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL */
    public function getOnUpdate()
    {
        return $this->onUpdate;
    }

    /**
     * @param self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL $onDelete
     *
     * @return $this
     */
    public function setOnDelete($onDelete)
    {
        $this->onDelete = $onDelete;

        return $this->setModified(true);
    }

    /** @return self::RESTRICT|self::NO_ACTION|self::CASCADE|self::SET_NULL */
    public function getOnDelete()
    {
        return $this->onDelete;
    }

    /** @return bool */
    public function equals(self $index)
    {
        return
            $this->name === $index->name
            && $this->table === $index->table
            && $this->columns === $index->columns
            && $this->onUpdate === $index->onUpdate
            && $this->onDelete === $index->onDelete;
    }
}
