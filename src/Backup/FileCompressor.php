<?php

namespace Redaxo\Core\Backup;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\File;

/**
 * @internal
 */
final class FileCompressor
{
    /**
     * GZIPs a file on disk (appending .gz to the name).
     *
     * From https://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
     * Based on function by Kioob at:
     * https://www.php.net/manual/en/function.gzwrite.php#34955
     *
     * @param string $source Path to file that should be compressed
     * @param int $level GZIP compression level (default: 9)
     * @return string|false New filename (with .gz appended) if success, or false if operation fails
     */
    public function gzCompress(string $source, int $level = 9): string|false
    {
        $dest = $source . '.gz';
        $mode = 'wb' . $level;
        $error = false;
        if ($fpOut = gzopen($dest, $mode)) {
            if ($fpIn = fopen($source, 'r')) {
                while (!feof($fpIn)) {
                    gzwrite($fpOut, fread($fpIn, 1024 * 512));
                }
                fclose($fpIn);
            } else {
                $error = true;
            }
            gzclose($fpOut);
        } else {
            $error = true;
        }
        if ($error) {
            return false;
        }

        return $dest;
    }

    /**
     * Read a gz compressed file into a plain string.
     *
     * @param string $source Path to a .gz file that should be decompressed
     * @return string|false The uncompressed content if success, or false if operation fails
     */
    public function gzReadDeCompressed(string $source): string|false
    {
        if ('gz' !== File::extension($source)) {
            throw new InvalidArgumentException('Expecting a file with .gz suffix');
        }

        $str = '';
        if ($fpIn = gzopen($source, 'r')) {
            while (!gzeof($fpIn)) {
                $str .= gzgets($fpIn, 1024 * 512);
            }
            gzclose($fpIn);
        } else {
            return false;
        }

        return $str;
    }
}
