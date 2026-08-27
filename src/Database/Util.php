<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\RuntimeException;

use function dirname;
use function sprintf;
use function strlen;

/**
 * Class to execute a sql dump.
 */
final readonly class Util
{
    private function __construct() {}

    /** @psalm-taint-escape file */
    public static function slowQueryLogPath(): ?string
    {
        $db = Sql::factory();
        $db->setQuery("show variables like 'slow_query_log_file'");
        $slowQueryLogPath = (string) $db->getValue('Value');

        if ('' !== $slowQueryLogPath) {
            if ('.' === dirname($slowQueryLogPath)) {
                $db->setQuery('select @@datadir as default_data_dir');
                $defaultDataDir = (string) $db->getValue('default_data_dir');

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

    /**
     * Importiert die gegebene SQL-Datei in die Datenbank.
     *
     * @param non-empty-string $file
     *
     * @throws SqlException
     */
    public static function importDump(string $file, bool $debug = false): void
    {
        if (!str_ends_with($file, '.sql')) {
            throw new InvalidArgumentException('Expecting a .sql file, "' . $file . '" given.');
        }

        $sql = Sql::factory();
        $sql->setDebug($debug);
        $error = '';

        foreach (self::readSqlDump($file) as $query) {
            try {
                $sql->setQuery(self::prepareQuery($query));
            } catch (SqlException $e) {
                $error .= $e->getMessage() . "\n<br />";
            }
        }
        if ($error) {
            throw new SqlException($error, null, $sql);
        }
    }

    private static function prepareQuery(string $query): string
    {
        // Core::getUser() gibts im Setup nicht
        /** @psalm-taint-escape sql */ // we trust the user db table
        $user = Core::getUser()->login ?? '';

        $query = str_replace('%USER%', $user, $query);
        $query = str_replace('%TIME%', (string) time(), $query);
        $query = str_replace('%TABLE_PREFIX%', Core::getTablePrefix(), $query);
        $query = str_replace('%TEMP_PREFIX%', Core::TEMP_PREFIX, $query);

        return $query;
    }

    /**
     * Reads a file and split all statements in it.
     *
     * @param non-empty-string $file Path to the SQL-dump-file
     * @return list<string>
     */
    private static function readSqlDump(string $file): array
    {
        if (is_file($file) && is_readable($file)) {
            $ret = [];
            $fileContent = file_get_contents($file);
            $sqlsplit = self::splitSqlFile($fileContent);

            foreach ($sqlsplit as $qry) {
                $ret[] = $qry['query'];
            }

            return $ret;
        }

        throw new RuntimeException('File "' . $file . '" could not be read.');
    }

    // Taken from phpmyadmin (read_dump.lib.php: PMA_splitSqlFile)

    /**
     * Removes comment lines and splits up large sql files into individual queries.
     *
     * Last revision: September 23, 2001 - gandon
     *
     * @param string $sql the sql commands
     * @return list<array{query: string, empty: bool}> the splitted sql commands
     */
    public static function splitSqlFile(string $sql): array
    {
        // do not trim, see bug #1030644
        // $sql          = trim($sql);
        $sql = rtrim($sql, "\n\r");
        $sqlLen = strlen($sql);
        $stringStart = '';
        $inString = false;
        $nothing = true;
        $time0 = time();
        $queries = [];

        for ($i = 0; $i < $sqlLen; ++$i) {
            $char = $sql[$i];

            // We are in a string, check for not escaped end of strings except for
            // backquotes that can't be escaped
            if ($inString) {
                while (true) {
                    $i = strpos($sql, $stringStart, $i);
                    // No end of string found -> add the current substring to the
                    // returned array
                    if (!$i) {
                        $queries[] = ['query' => $sql, 'empty' => $nothing];
                        return $queries;
                    }
                    // Backquotes or no backslashes before quotes: it's indeed the
                    // end of the string -> exit the loop
                    if ('`' == $stringStart || '\\' != $sql[$i - 1]) {
                        $stringStart = '';
                        $inString = false;
                        break;
                    }
                    // one or more Backslashes before the presumed end of string...

                    // ... first checks for escaped backslashes
                    $j = 2;
                    $escapedBackslash = false;
                    while ($i - $j > 0 && '\\' == $sql[$i - $j]) {
                        $escapedBackslash = !$escapedBackslash;
                        ++$j;
                    }
                    // ... if escaped backslashes: it's really the end of the
                    // string -> exit the loop
                    if ($escapedBackslash) {
                        $stringStart = '';
                        $inString = false;
                        break;
                    }
                    // ... else loop

                    ++$i;

                    // end if...elseif...else
                } // end for
            } // end if (in string)

            // lets skip comments (/*, -- and #)
            elseif (('-' == $char && $sqlLen > $i + 2 && '-' == $sql[$i + 1] && $sql[$i + 2] <= ' ') || '#' == $char || ('/' == $char && $sqlLen > $i + 1 && '*' == $sql[$i + 1])) {
                $i = strpos($sql, '/' == $char ? '*/' : "\n", $i);
                // didn't we hit end of string?
                if (false === $i) {
                    break;
                }
                if ('/' == $char) {
                    ++$i;
                }
            }

            // We are not in a string, first check for delimiter...
            elseif (';' == $char) {
                // if delimiter found, add the parsed part to the returned array
                $queries[] = ['query' => substr($sql, 0, $i), 'empty' => $nothing];
                $nothing = true;
                $sql = ltrim(substr($sql, min($i + 1, $sqlLen)));
                $sqlLen = strlen($sql);
                if ($sqlLen) {
                    /** @psalm-suppress LoopInvalidation */
                    $i = -1;
                } else {
                    // The submited statement(s) end(s) here
                    return $queries;
                }
            } // end else if (is delimiter)

            // ... then check for start of a string,...
            elseif (('"' == $char) || ("'" == $char) || ('`' == $char)) {
                $inString = true;
                $nothing = false;
                $stringStart = $char;
            } // end else if (is start of string)

            elseif ($nothing) {
                $nothing = false;
            }

            // loic1: send a fake header each 30 sec. to bypass browser timeout
            $time1 = time();
            if ($time1 >= $time0 + 30) {
                $time0 = $time1;
                header('X-pmaPing: Pong');
            } // end if
        } // end for

        // add any rest to the returned array
        if (!empty($sql) && preg_match('@[^[:space:]]+@', $sql)) {
            $queries[] = ['query' => $sql, 'empty' => $nothing];
        }

        return $queries;
    }
}
