<?php

/**
 * Class for handling directories.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_dir
{
    /**
     * Creates a directory.
     *
     * @param string $dir Path of the new directory
     * @param bool $recursive When FALSE, nested directories won't be created
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $dir
     */
    public static function create($dir, $recursive = true)
    {
        if (is_dir($dir)) {
            return true;
        }

        $parent = dirname($dir);
        if (!is_dir($parent) && (!$recursive || !self::create($parent))) {
            return false;
        }

        if (!self::isWritable($parent)) {
            return false;
        }

        // suppress "File exists" warning in case of concurrent processes
        @mkdir($dir, rex::getDirPerm());

        if (is_dir($dir)) {
            @chmod($dir, rex::getDirPerm());
            return true;
        }

        return false;
    }

    /**
     * Returns wether the directory is writable.
     *
     * @param string $dir Path of the directory
     *
     * @return bool
     *
     * @psalm-assert-if-true =non-empty-string $dir
     */
    public static function isWritable($dir)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        return @is_dir($dir) && @is_writable($dir . DIRECTORY_SEPARATOR . '.');
    }

    /**
     * Copies a directory.
     *
     * @param string $srcdir Path of the source directory
     * @param string $dstdir Path of the destination directory
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $srcdir
     * @psalm-assert-if-true =non-empty-string $dstdir
     */
    public static function copy($srcdir, $dstdir)
    {
        $srcdir = rtrim($srcdir, DIRECTORY_SEPARATOR);
        $dstdir = rtrim($dstdir, DIRECTORY_SEPARATOR);

        if (!self::create($dstdir)) {
            return false;
        }

        $state = true;

        foreach (rex_finder::factory($srcdir)->recursive() as $srcfilepath => $srcfile) {
            $dstfile = $dstdir . substr($srcfilepath, strlen($srcdir));
            if ($srcfile->isDir()) {
                $state = self::create($dstfile) && $state;
            } else {
                $state = rex_file::copy($srcfilepath, $dstfile) && $state;
            }
        }

        return $state;
    }

    /**
     * Deletes a directory.
     *
     * @param string $dir Path of the directory
     * @param bool $deleteSelf When FALSE, only subdirectories and files will be deleted
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public static function delete($dir, $deleteSelf = true)
    {
        if (!is_dir($dir)) {
            return true;
        }

        if (!self::deleteIterator(rex_finder::factory($dir)->recursive()->childFirst()->ignoreSystemStuff(false))) {
            return false;
        }

        // ignore warning "Directory not empty", there may already exist new files created by other page views
        return !$deleteSelf || @rmdir($dir);
    }

    /**
     * Deletes the files in a directory.
     *
     * @param string $dir Path of the directory
     * @param bool $recursive When FALSE, files in subdirectories won't be deleted
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public static function deleteFiles($dir, $recursive = true)
    {
        $iterator = rex_finder::factory($dir)->recursive($recursive)->filesOnly()->ignoreSystemStuff(false);
        return self::deleteIterator($iterator);
    }

    /**
     * Deletes files and directories by a rex_dir_iterator.
     *
     * @param Traversable<array-key, SplFileInfo> $iterator Iterator, $iterator->current() must return a SplFileInfo-Object
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public static function deleteIterator(Traversable $iterator)
    {
        $state = true;

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                // ignore warning "Directory not empty", there may already exist new files created by other page views
                $state = @rmdir((string) $file) && $state;
            } else {
                $state = rex_file::delete((string) $file) && $state;
            }
        }

        return $state;
    }
}
