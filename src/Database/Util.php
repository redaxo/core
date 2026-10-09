<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Exception\RuntimeException;

use function dirname;
use function sprintf;

/**
 * Database helpers.
 */
final readonly class Util
{
    private function __construct() {}

    /** @psalm-taint-escape file */
    public static function slowQueryLogPath(): ?string
    {
        $db = Sql::factory();
        $db->setQuery("show variables like 'slow_query_log_file'");
        $slowQueryLogPath = $db->getStringValue('Value');

        if ('' !== $slowQueryLogPath) {
            if ('.' === dirname($slowQueryLogPath)) {
                $db->setQuery('select @@datadir as default_data_dir');
                $defaultDataDir = $db->getStringValue('default_data_dir');

                return $defaultDataDir . $slowQueryLogPath;
            }

            return $slowQueryLogPath;
        }

        return null;
    }

    /**
     * Copy the table structure (without its data) to another table.
     *
     * @param non-empty-string $sourceTable
     * @param non-empty-string $destinationTable
     */
    public static function copyTable(string $sourceTable, string $destinationTable): void
    {
        if (!Table::get($sourceTable)->exists()) {
            throw new RuntimeException(sprintf('Source table "%s" does not exist.', $sourceTable));
        }

        if (Table::get($destinationTable)->exists()) {
            throw new RuntimeException(sprintf('Destination table "%s" already exists.', $destinationTable));
        }

        $sql = Sql::factory();
        $sql->setQuery('CREATE TABLE ' . $sql->escapeIdentifier($destinationTable) . ' LIKE ' . $sql->escapeIdentifier($sourceTable));

        Table::clearInstance($destinationTable);
    }

    /**
     * Copy the table structure and its data to another table.
     *
     * @param non-empty-string $sourceTable
     * @param non-empty-string $destinationTable
     */
    public static function copyTableWithData(string $sourceTable, string $destinationTable): void
    {
        self::copyTable($sourceTable, $destinationTable);

        $sql = Sql::factory();
        $sql->setQuery('INSERT ' . $sql->escapeIdentifier($destinationTable) . ' SELECT * FROM ' . $sql->escapeIdentifier($sourceTable));
    }

    /**
     * Allgemeine funktion die eine Datenbankspalte fortlaufend durchnummeriert.
     * Dies ist z.B. nützlich beim Umgang mit einer Prioritäts-Spalte.
     *
     * @param non-empty-string $tableName Name der Datenbanktabelle
     * @param non-empty-string $prioColumnName Name der Spalte in der Tabelle, in der die Priorität (Integer) gespeichert wird
     * @param string $whereCondition Where-Bedingung zur Einschränkung des ResultSets
     * @param string $orderBy Sortierung des ResultSets
     * @param int $startBy Startpriorität
     */
    public static function organizePriorities(string $tableName, string $prioColumnName, string $whereCondition = '', string $orderBy = '', int $startBy = 1): void
    {
        // Datenbankvariable initialisieren
        $qry = 'SET @count=' . ($startBy - 1);
        $sql = Sql::factory();
        $sql->setQuery($qry);

        // Spalte updaten
        $qry = 'UPDATE ' . $tableName . ' SET ' . $prioColumnName . ' = ( SELECT @count := @count +1 )';

        if ('' != $whereCondition) {
            $qry .= ' WHERE ' . $whereCondition;
        }

        if ('' != $orderBy) {
            $qry .= ' ORDER BY ' . $orderBy;
        }

        $sql->setQuery($qry);
    }
}
