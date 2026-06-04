<?php

namespace Redaxo\Core\MetaInfo\Database;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;

/**
 * @internal
 */
final readonly class Table
{
    public const int FIELD_TEXT = 1;
    public const int FIELD_TEXTAREA = 2;
    public const int FIELD_SELECT = 3;
    public const int FIELD_RADIO = 4;
    public const int FIELD_CHECKBOX = 5;
    public const int FIELD_REX_MEDIA_WIDGET = 6;
    public const int FIELD_REX_LINK_WIDGET = 8;
    public const int FIELD_DATE = 10;
    public const int FIELD_DATETIME = 11;
    public const int FIELD_LEGEND = 12;
    public const int FIELD_TIME = 13;
    public const int FIELD_COUNT = 13;

    /** @param positive-int $DBID */
    public function __construct(
        private string $tableName,
        private int $DBID = 1,
    ) {}

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function addColumn(string $name, string $type, ?int $length, ?string $default = null, bool $nullable = true): void
    {
        $sql = Sql::factory($this->DBID);

        $qry = 'ALTER TABLE ' . $sql->escapeIdentifier($this->tableName) . ' ADD ';
        $qry .= $sql->escapeIdentifier($name);

        if (!ctype_alpha($type)) {
            throw new InvalidArgumentException('Invalid column type "' . $type . '"');
        }
        /** @psalm-taint-escape sql */
        $addType = ' ' . $type;
        $qry .= $addType;

        if (0 != $length) {
            $qry .= '(' . (int) $length . ')';
        }

        // `text` columns in mysql can not have default values
        if ('text' !== $type && null !== $default) {
            $qry .= ' DEFAULT ' . $sql->escape($default);
        }

        if (!$nullable) {
            $qry .= ' NOT NULL';
        }

        $sql->setQuery($qry);
    }

    public function editColumn(string $oldname, string $name, string $type, ?int $length, ?string $default = null, bool $nullable = true): void
    {
        $sql = Sql::factory($this->DBID);

        $qry = 'ALTER TABLE ' . $sql->escapeIdentifier($this->tableName) . ' CHANGE ';
        $qry .= $sql->escapeIdentifier($oldname) . ' ' . $sql->escapeIdentifier($name);

        if (!ctype_alpha($type)) {
            throw new InvalidArgumentException('Invalid column type "' . $type . '"');
        }
        /** @psalm-taint-escape sql */
        $addType = ' ' . $type;
        $qry .= $addType;

        if (0 != $length) {
            $qry .= '(' . (int) $length . ')';
        }

        // `text` columns in mysql can not have default values
        if ('text' !== $type && null !== $default) {
            $qry .= ' DEFAULT ' . $sql->escape($default);
        }

        if (!$nullable) {
            $qry .= ' NOT NULL';
        }

        $sql->setQuery($qry);
    }

    public function deleteColumn(string $name): void
    {
        $sql = Sql::factory($this->DBID);

        $qry = 'ALTER TABLE ' . $sql->escapeIdentifier($this->tableName) . ' DROP ';
        $qry .= $sql->escapeIdentifier($name);

        $sql->setQuery($qry);
    }

    public function hasColumn(string $name): bool
    {
        $columns = Sql::showColumns($this->tableName, $this->DBID);

        foreach ($columns as $column) {
            if ($column['name'] == $name) {
                return true;
            }
        }
        return false;
    }
}
