<?php

/**
 * Class to execute a sql dump.
 *
 * @package redaxo\core\sql
 */
class rex_sql_util
{
    /** @psalm-taint-escape file */
    public static function slowQueryLogPath(): ?string
    {
        $db = rex_sql::factory();
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
     *
     * @throws rex_exception
     */
    public static function copyTable(string $sourceTable, string $destinationTable): void
    {
        if (!rex_sql_table::get($sourceTable)->exists()) {
            throw new rex_exception(sprintf('Source table "%s" does not exist.', $sourceTable));
        }

        if (rex_sql_table::get($destinationTable)->exists()) {
            throw new rex_exception(sprintf('Destination table "%s" already exists.', $destinationTable));
        }

        $sql = rex_sql::factory();
        $sql->setQuery('CREATE TABLE ' . $sql->escapeIdentifier($destinationTable) . ' LIKE ' . $sql->escapeIdentifier($sourceTable));

        rex_sql_table::clearInstance($destinationTable);
    }

    /**
     * Copy the table structure and its data to another table.
     *
     * @param non-empty-string $sourceTable
     * @param non-empty-string $destinationTable
     *
     * @throws rex_exception
     */
    public static function copyTableWithData(string $sourceTable, string $destinationTable): void
    {
        self::copyTable($sourceTable, $destinationTable);

        $sql = rex_sql::factory();
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
     * @return void
     */
    public static function organizePriorities($tableName, $prioColumnName, $whereCondition = '', $orderBy = '', $startBy = 1)
    {
        // Datenbankvariable initialisieren
        $qry = 'SET @count=' . ($startBy - 1);
        $sql = rex_sql::factory();
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
     * @param bool $debug
     *
     * @throws rex_sql_exception
     *
     * @return bool true bei Erfolg
     */
    public static function importDump($file, $debug = false)
    {
        if (!str_ends_with($file, '.sql')) {
            throw new rex_exception('Expecting a .sql file, "' . $file . '" given.');
        }

        $sql = rex_sql::factory();
        $sql->setDebug($debug);
        $error = '';

        foreach (self::readSqlDump($file) as $query) {
            try {
                $sql->setQuery(self::prepareQuery($query));
            } catch (rex_sql_exception $e) {
                $error .= $e->getMessage() . "\n<br />";
            }
        }
        if ($error) {
            throw new rex_sql_exception($error, null, $sql);
        }

        return true;
    }

    /** @return string */
    private static function prepareQuery(string $query)
    {
        // rex::getUser() gibts im Setup nicht
        /** @psalm-taint-escape sql */ // we trust the user db table
        $user = rex::getUser()?->getLogin() ?? '';

        $query = str_replace('%USER%', $user, $query);
        $query = str_replace('%TIME%', (string) time(), $query);
        $query = str_replace('%TABLE_PREFIX%', rex::getTablePrefix(), $query);
        $query = str_replace('%TEMP_PREFIX%', rex::getTempPrefix(), $query);

        return $query;
    }

    /**
     * Reads a file and split all statements in it.
     *
     * @param non-empty-string $file Path to the SQL-dump-file
     *
     * @return array
     */
    private static function readSqlDump($file)
    {
        if (is_file($file) && is_readable($file)) {
            $ret = [];
            $sqlsplit = [];
            $fileContent = file_get_contents($file);
            self::splitSqlFile($sqlsplit, $fileContent, 0);

            if (is_array($sqlsplit)) {
                foreach ($sqlsplit as $qry) {
                    $ret[] = $qry['query'];
                }
            }

            return $ret;
        }

        throw new rex_exception('File "' . $file . '" could not be read.');
    }

    // Taken from phpmyadmin (read_dump.lib.php: PMA_splitSqlFile)

    /**
     * Removes comment lines and splits up large sql files into individual queries.
     *
     * Last revision: September 23, 2001 - gandon
     *
     * @param array $queries the splitted sql commands
     * @param string $sql the sql commands
     * @param int $release the MySQL release number (because certains php3 versions
     *                     can't get the value of a constant from within a function)
     *
     * @return bool always true
     */
    public static function splitSqlFile(&$queries, $sql, $release)
    {
        // do not trim, see bug #1030644
        // $sql          = trim($sql);
        $sql = rtrim($sql, "\n\r");
        $sqlLen = strlen($sql);
        $stringStart = '';
        $inString = false;
        $nothing = true;
        $time0 = time();

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
                        $queries[] = $sql;
                        return true;
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
                    return true;
                }
            } // end else if (is delimiter)

            // ... then check for start of a string,...
            elseif (('"' == $char) || ('\'' == $char) || ('`' == $char)) {
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

        return true;
    }
}
