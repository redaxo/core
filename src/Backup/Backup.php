<?php

namespace Redaxo\Core\Backup;

use PDO;
use Redaxo\Core\Cache;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;

use function count;
use function is_int;
use function Redaxo\Core\View\escape;
use function sprintf;
use function strlen;

class Backup
{
    public const IMPORT_ARCHIVE = 1;
    public const IMPORT_DB = 2;
    public const IMPORT_EVENT_PRE = 3;
    public const IMPORT_EVENT_POST = 4;

    /** @return string */
    public static function getDir()
    {
        $dir = Path::coreData('backup');
        Dir::create($dir);

        return $dir;
    }

    /** @param self::IMPORT_* $importType */
    public static function isFilenameValid(int $importType, string $filename): bool
    {
        if (self::IMPORT_ARCHIVE === $importType) {
            return str_ends_with($filename, '.tar.gz');
        }
        if (self::IMPORT_DB === $importType) {
            return str_ends_with($filename, '.sql') || str_ends_with($filename, '.sql.gz');
        }

        throw new LogicException('Unexpected importType "' . $importType . '".');
    }

    /**
     * @param self::IMPORT_*|string $fileType
     *
     * @return list<string>
     */
    public static function getBackupFiles($fileType)
    {
        $dir = self::getDir();

        $folder = Finder::factory($dir)->filesOnly();

        $filtered = [];
        foreach ($folder as $file) {
            $file = $file->getFilename();
            if (is_int($fileType)) {
                if (self::isFilenameValid($fileType, $file)) {
                    $filtered[] = $file;
                }
            } else {
                // bc compat
                $fileSuffix = $fileType;
                if (substr($file, strlen($file) - strlen($fileSuffix)) == $fileSuffix) {
                    $filtered[] = $file;
                }
            }
        }
        $folder = $filtered;

        usort($folder, static function ($fileA, $fileB) {
            return $fileA <=> $fileB;
        });

        return $folder;
    }

    /**
     * Importiert den SQL Dump $filename in die Datenbank.
     *
     * @param string $filename Pfad + Dateinamen zur SQL-Datei
     *
     * @return array{state: bool, message: string} Gibt ein Assoc. Array zurück.
     *               'state' => boolean (Status ob fehler aufgetreten sind)
     *               'message' => Evtl. Status/Fehlermeldung
     */
    public static function importDb($filename)
    {
        /** @return array{state: bool, message: string} */
        $returnError = static function (string $message): array {
            return ['state' => false, 'message' => $message];
        };

        if ('' == $filename || !self::isFilenameValid(self::IMPORT_DB, $filename)) {
            return $returnError(I18n::msg('backup_no_import_file_chosen_or_wrong_version') . '<br>');
        }

        if ('gz' === File::extension($filename)) {
            $compressor = new FileCompressor();
            $conts = $compressor->gzReadDeCompressed($filename);

            // should not happen
            if (false === $conts) {
                return $returnError(I18n::msg('backup_no_valid_import_file') . '. Unable to decompress .gz');
            }
        } else {
            $conts = File::require($filename);
        }

        // Versionsstempel prüfen
        // ## Redaxo Database Dump Version x.x
        $mainVersion = Core::getVersion('%s');
        $version = strpos($conts, '## Redaxo Database Dump Version ' . $mainVersion);
        if (false === $version) {
            return $returnError(I18n::msg('backup_no_valid_import_file') . '. [## Redaxo Database Dump Version ' . $mainVersion . '] is missing');
        }
        // Versionsstempel entfernen
        $conts = trim(str_replace('## Redaxo Database Dump Version ' . $mainVersion, '', $conts));

        // Prefix prüfen
        // ## Prefix xxx_
        if (preg_match('/^## Prefix ([a-zA-Z0-9\_]*)/', $conts, $matches) && isset($matches[1])) {
            // prefix entfernen
            $prefix = $matches[1];
            $conts = trim(str_replace('## Prefix ' . $prefix, '', $conts));
        } else {
            // Prefix wurde nicht gefunden
            return $returnError(I18n::msg('backup_no_valid_import_file') . '. [## Prefix ' . Core::getTablePrefix() . '] is missing');
        }

        // Prefix im export mit dem der installation angleichen
        if (Core::getTablePrefix() != $prefix) {
            // Hier case-insensitiv ersetzen, damit alle möglich Schreibweisen (TABLE TablE, tAblE,..) ersetzt werden
            // Dies ist wichtig, da auch SQLs innerhalb von Ein/Ausgabe der Module vom rex-admin verwendet werden
            $conts = preg_replace('/(TABLES? `?)' . preg_quote($prefix, '/') . '/i', '$1' . Core::getTablePrefix(), $conts);
            $conts = preg_replace('/(INTO `?)' . preg_quote($prefix, '/') . '/i', '$1' . Core::getTablePrefix(), $conts);
            $conts = preg_replace('/(EXISTS `?)' . preg_quote($prefix, '/') . '/i', '$1' . Core::getTablePrefix(), $conts);
        }

        // ----- EXTENSION POINT
        $filesize = filesize($filename);
        $msg = '';
        $msg = Extension::registerPoint(new ExtensionPoint('BACKUP_BEFORE_DB_IMPORT', $msg, [
            'content' => $conts,
            'filename' => $filename,
            'filesize' => $filesize,
        ]));

        // require import skript to do some userside-magic
        self::importScript(str_replace('.sql', '.php', $filename), self::IMPORT_DB, self::IMPORT_EVENT_PRE);

        // Datei aufteilen
        $lines = Util::splitSqlFile($conts);

        $error = [];

        $sql = Sql::factory();
        foreach ($lines as $line) {
            try {
                $sql->setQuery($line['query']);
            } catch (SqlException $e) {
                $error[] = nl2br(trim(escape($e->getMessage())));
            }
        }

        if ($error) {
            return $returnError(implode('<br/>', $error));
        }

        $msg .= I18n::msg('backup_database_imported') . '. ' . I18n::msg('backup_entry_count', (string) count($lines)) . '<br />';
        unset($lines);

        // delete cache before EP to avoid obsolete caches while running extensions
        Cache::delete();

        // refresh Config with new values from database
        Config::refresh();

        // ----- EXTENSION POINT
        $msg = Extension::registerPoint(new ExtensionPoint('BACKUP_AFTER_DB_IMPORT', $msg, [
            'content' => $conts,
            'filename' => $filename,
            'filesize' => $filesize,
        ]));

        // require import skript to do some userside-magic
        self::importScript(str_replace('.sql', '.php', $filename), self::IMPORT_DB, self::IMPORT_EVENT_POST);

        // delete cache again because the extensions and the php script could have changed data again
        $msg .= Cache::delete();

        return ['state' => true, 'message' => $msg];
    }

    /**
     * Importiert das Tar-Archiv $filename in den Ordner /files.
     *
     * @param string $filename Pfad + Dateinamen zum Tar-Archiv
     *
     * @return array{state: bool, message: string} Gibt ein Assoc. Array zurück.
     *               'state' => boolean (Status ob fehler aufgetreten sind)
     *               'message' => Evtl. Status/Fehlermeldung
     */
    public static function importFiles($filename)
    {
        $return = [];
        $return['state'] = false;

        if ('' == $filename || !self::isFilenameValid(self::IMPORT_ARCHIVE, $filename)) {
            $return['message'] = I18n::msg('backup_no_import_file_chosen') . '<br />';
            return $return;
        }

        $tar = new Tar();

        // ----- EXTENSION POINT
        /**
         * @var Tar $tar
         * @psalm-ignore-var
         */
        $tar = Extension::registerPoint(new ExtensionPoint('BACKUP_BEFORE_FILE_IMPORT', $tar));

        // require import skript to do some userside-magic
        self::importScript(str_replace('.tar.gz', '.php', $filename), self::IMPORT_ARCHIVE, self::IMPORT_EVENT_PRE);

        $tar->openTAR($filename);
        $tar->extractTar(Path::base());
        $msg = I18n::msg('backup_file_imported') . '<br />';

        // ----- EXTENSION POINT
        Extension::registerPoint(new ExtensionPoint('BACKUP_AFTER_FILE_IMPORT', $tar));

        // require import skript to do some userside-magic
        self::importScript(str_replace('.tar.gz', '.php', $filename), self::IMPORT_ARCHIVE, self::IMPORT_EVENT_POST);

        $return['state'] = true;
        $return['message'] = $msg;
        return $return;
    }

    /**
     * Erstellt einen SQL Dump, der die aktuellen Datebankstruktur darstellt.
     * Dieser wird in der Datei $filename gespeichert.
     *
     * @param string $filename
     * @param array<string>|null $tables
     *
     * @return bool TRUE wenn ein Dump erstellt wurde, sonst FALSE
     */
    public static function exportDb($filename, ?array $tables = null)
    {
        $fp = @tmpfile();
        $tempCacheFile = null;

        // in case of permission issues/misconfigured tmp-folders
        if (!$fp) {
            $tempCacheFile = Path::cache(Path::basename($filename));
            $fp = fopen($tempCacheFile, 'w');
            if (!$fp) {
                return false;
            }
        }

        $sql = Sql::factory();

        $nl = "\n";
        $insertSize = 4000;

        // ----- EXTENSION POINT
        Extension::registerPoint(new ExtensionPoint('BACKUP_BEFORE_DB_EXPORT'));

        // Versionsstempel hinzufügen
        fwrite($fp, '## Redaxo Database Dump Version ' . Core::getVersion('%s') . $nl);
        fwrite($fp, '## Prefix ' . Core::getTablePrefix() . $nl);
        //  fwrite($fp, '/*!40110 START TRANSACTION; */'.$nl);

        fwrite($fp, 'SET FOREIGN_KEY_CHECKS = 0;' . $nl . $nl);

        if (null === $tables) {
            $tables = self::getTables();
        }
        foreach ($tables as $table) {
            // ---- export metadata
            $create = Sql::showCreateTable($table);

            fwrite($fp, 'DROP TABLE IF EXISTS ' . $sql->escapeIdentifier($table) . ';' . $nl);
            fwrite($fp, $create . ';' . $nl);

            $fields = [];

            foreach ($sql->getArray('SHOW FIELDS FROM ' . $sql->escapeIdentifier($table)) as $field) {
                $type = (string) $field['Type'];
                if (preg_match('#^(bigint|int|smallint|mediumint|tinyint|timestamp)#i', $type)) {
                    $type = 'int';
                } elseif (preg_match('#^(float|double|decimal)#', $type)) {
                    $type = 'double';
                } elseif (preg_match('#^(char|varchar|text|longtext|mediumtext|tinytext)#', $type)) {
                    $type = 'string';
                } elseif (preg_match('#^(date|datetime|time|timestamp|year)#', $type)) {
                    // types which can be passed tru 1:1 as escaping isn't necessary, because we know the mysql internal format.
                    $type = 'raw';
                } else {
                    $type = 'unknown';
                }

                $fields[] = $type;
            }

            // ---- export tabledata
            $start = 0;
            $max = $insertSize;

            self::exportTable($table, $start, $max, $fp, $nl, $fields);

            if ($start > 0) {
                fwrite($fp, $nl . '/*!40000 ALTER TABLE ' . $sql->escapeIdentifier($table) . ' ENABLE KEYS */;');
                fwrite($fp, $nl . 'UNLOCK TABLES;' . $nl . $nl);
            }
        }

        fwrite($fp, 'SET FOREIGN_KEY_CHECKS = 1;' . $nl);

        $hasContent = true;

        // Den Dateiinhalt geben wir nur dann weiter, wenn es unbedingt notwendig ist.
        if (Extension::isRegistered('BACKUP_AFTER_DB_EXPORT')) {
            $content = File::require($filename);
            $hashBefore = md5($content);
            // ----- EXTENSION POINT
            $content = Extension::registerPoint(new ExtensionPoint('BACKUP_AFTER_DB_EXPORT', $content));
            $hashAfter = md5($content);

            if ($hashAfter != $hashBefore) {
                File::put($filename, $content);
                $hasContent = !empty($content);
                unset($content);
            }
        }

        // Wenn das backup vollständig und erfolgreich erzeugt werden konnte, den Export 1:1 ans Ziel kopieren.
        if ($tempCacheFile) {
            fclose($fp);
            rename($tempCacheFile, $filename);
        } else {
            $destination = fopen($filename, 'w');
            rewind($fp);
            if (!$destination) {
                return false;
            }
            stream_copy_to_stream($fp, $destination);
            fclose($fp);
            fclose($destination);
        }

        return $hasContent;
    }

    /**
     * Exportiert alle Ordner $folders aus dem Verzeichnis /files.
     * Wenn $archivePath übergeben wird, wird das Achive mittels Streaming gebaut, sodass sehr große Exporte möglich sind.
     *
     * @param array<string> $folders Array von Ordnernamen, die exportiert werden sollen
     * @param string|null $archivePath Pfad, wo das archiv angelegt werden soll
     *
     * @return string|null Inhalt des Tar-Archives als string, wenn $archivePath nicht uebergeben wurde - sonst null
     */
    public static function exportFiles($folders, $archivePath = null)
    {
        if (null == $archivePath) {
            $tmpArchivePath = false;
            try {
                $tmpArchivePath = tempnam(sys_get_temp_dir(), 'rex-file-export');

                self::streamExport($folders, $tmpArchivePath);
                return File::get($tmpArchivePath);
            } finally {
                if ($tmpArchivePath) {
                    File::delete($tmpArchivePath);
                }
            }
        }

        self::streamExport($folders, $archivePath);
        return null;
    }

    /**
     * @param array<string> $folders
     * @param string $archivePath
     * @return void
     */
    private static function streamExport($folders, $archivePath)
    {
        $tar = new Tar();
        $tar->create($archivePath);

        // ----- EXTENSION POINT
        $tar = Extension::registerPoint(new ExtensionPoint('BACKUP_BEFORE_FILE_EXPORT', $tar));

        foreach ($folders as $item) {
            self::addFolderToTar($tar, Url::frontend(), $item);
        }

        // ----- EXTENSION POINT
        $tar = Extension::registerPoint(new ExtensionPoint('BACKUP_AFTER_FILE_EXPORT', $tar));

        $tar->close();
    }

    /**
     * Fügt einem Tar-Archiv ein Ordner von Dateien hinzu.
     *
     * @param string $path
     * @param string $dir
     * @return void
     */
    private static function addFolderToTar(Tar $tar, $path, $dir)
    {
        $handle = opendir($path . $dir);

        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to open dir "%s".', $path . $dir));
        }

        $isMediafolder = realpath($path . $dir) . '/' == Path::media();
        while (false !== ($file = readdir($handle))) {
            // Alles exportieren, außer ...
            // - addons verzeichnis im mediafolder (wird bei addoninstallation wiedererstellt)
            // - svn infos
            // - tmp prefix Dateien

            if ('.' == $file || '..' == $file || '.svn' == $file) {
                continue;
            }

            if (str_starts_with($file, Core::getTempPrefix())) {
                continue;
            }

            if ($isMediafolder && 'addons' == $file) {
                continue;
            }

            if (is_dir($path . $dir . '/' . $file)) {
                self::addFolderToTar($tar, $path . $dir . '/', $file);
            } else {
                $tar->addFile($path . $dir . '/' . $file);
            }
        }
        closedir($handle);
    }

    /** @return list<string> */
    public static function getTables()
    {
        $tables = [];
        foreach (Sql::factory()->getTables(Core::getTablePrefix()) as $table) {
            if (!str_starts_with($table, Core::getTablePrefix() . Core::getTempPrefix())) { // Tabellen die mit rex_tmp_ beginnne, werden nicht exportiert!
                $tables[] = $table;
            }
        }
        return $tables;
    }

    /**
     * @param string $filename
     * @param self::IMPORT_ARCHIVE|self::IMPORT_DB $importType
     * @param self::IMPORT_EVENT_* $eventType
     * @return void
     */
    private static function importScript($filename, $importType, $eventType)
    {
        if (is_file($filename)) {
            require $filename;
        }
    }

    /**
     * @param string $table
     * @param int $start
     * @param int $max
     * @param resource $fp
     * @param string $nl
     * @param list<string> $fields
     * @return void
     */
    private static function exportTable($table, &$start, $max, $fp, $nl, array $fields)
    {
        do {
            $sql = Sql::factory();
            $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($table) . ' LIMIT ' . $start . ',' . $max);
            $count = $sql->getRows();

            if ($count > 0 && 0 == $start) {
                fwrite($fp, $nl . 'LOCK TABLES ' . $sql->escapeIdentifier($table) . ' WRITE;');
                fwrite($fp, $nl . '/*!40000 ALTER TABLE ' . $sql->escapeIdentifier($table) . ' DISABLE KEYS */;');
            } elseif (0 == $count) {
                break;
            }

            $start += $max;
            $values = [];

            foreach ($sql as $row) {
                $record = [];
                $array = $row->getRow(PDO::FETCH_NUM);

                foreach ($fields as $idx => $type) {
                    $column = $array[$idx];

                    if (null === $column) {
                        $record[] = 'NULL';

                        continue;
                    }

                    switch ($type) {
                        // prevent calling sql->escape() on values with a known format
                        case 'raw':
                            $record[] = "'" . $column . "'";
                            break;
                        case 'int':
                            $record[] = (int) $column;
                            break;
                        case 'double':
                            $record[] = sprintf('%.10F', (float) $column);
                            break;
                        case 'string':
                            $column = (string) $column;

                            // fast-exit for very frequent used harmless values
                            if ('0' === $column || '' === $column || ' ' === $column || '|' === $column || '||' === $column) {
                                $record[] = "'" . $column . "'";
                                break;
                            }

                            // fast-exit for very frequent used harmless values
                            if (strlen($column) <= 3 && ctype_alnum($column)) {
                                $record[] = "'" . $column . "'";
                                break;
                            }
                            // no break
                        default:
                            $record[] = $sql->escape((string) $column);
                            break;
                    }
                }

                $values[] = $nl . '  (' . implode(',', $record) . ')';
            }

            if (!empty($values)) {
                fwrite($fp, $nl . 'INSERT INTO ' . $sql->escapeIdentifier($table) . ' VALUES ');

                // iterate the values instead of implode() to save a few MB memory
                $numValues = count($values);
                $lastIdx = $numValues - 1;
                for ($i = 0; $i < $numValues; ++$i) {
                    if ($i == $lastIdx) {
                        fwrite($fp, $values[$i]);
                    } else {
                        fwrite($fp, $values[$i] . ',');
                    }
                }
                unset($values);

                fwrite($fp, ';');
            }
        } while ($count >= $max);
    }
}
