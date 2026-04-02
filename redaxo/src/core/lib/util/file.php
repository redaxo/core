<?php

/**
 * Class for handling files.
 *
 * @package redaxo\core
 */
class rex_file
{
    /**
     * Returns the content of a file.
     *
     * @param string $file Path to the file
     *
     * @throws rex_exception throws when the file cannot be read
     *
     * @return string Content of the file
     *
     * @psalm-assert non-empty-string $file
     */
    public static function require(string $file): string
    {
        return rex_timer::measure(__METHOD__, static function () use ($file) {
            $content = @file_get_contents($file);

            if (false === $content) {
                throw new rex_exception('Unable to read file "' . $file . '"');
            }

            return $content;
        });
    }

    /**
     * Returns the content of a file.
     *
     * @template T
     * @param string $file Path to the file
     * @param T $default Default value
     * @return string|T Content of the file or default value if the file isn't readable
     */
    public static function get($file, $default = null)
    {
        return rex_timer::measure(__METHOD__, static function () use ($file, $default) {
            $content = @file_get_contents($file);
            return false !== $content ? $content : $default;
        });
    }

    /**
     * Returns the content of a config file.
     *
     * @template T
     * @param string $file Path to the file
     * @param T $default Default value
     * @return array<mixed>|T Content of the file or default value if the file isn't readable
     */
    public static function getConfig($file, $default = [])
    {
        $content = self::get($file);
        return null === $content ? $default : rex_string::yamlDecode($content);
    }

    /**
     * Returns the content of a cache file.
     *
     * @template T
     * @param string $file Path to the file
     * @param T $default Default value
     * @return array<mixed>|T Content of the file or default value if the file isn't readable
     */
    public static function getCache($file, $default = [])
    {
        $content = self::get($file);
        return null === $content ? $default : json_decode($content, true);
    }

    /**
     * Puts content in a file.
     *
     * @param string $file Path to the file
     * @param string $content Content for the file
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $file
     */
    public static function put($file, $content)
    {
        return rex_timer::measure(__METHOD__, static function () use ($file, $content) {
            if (!rex_dir::create(dirname($file)) || is_file($file) && !is_writable($file)) {
                return false;
            }

            // mimic a atomic write
            $tmpFile = @tempnam(dirname($file), rex_path::basename($file));
            if (false !== file_put_contents($tmpFile, $content) && self::move($tmpFile, $file)) {
                @chmod($file, rex::getFilePerm());
                return true;
            }
            @unlink($tmpFile);

            return false;
        });
    }

    /**
     * Appends content to a file.
     *
     * @param string $file Path to the file
     * @param string $content Content for the file
     * @param string $delimiter delimiter for new Content
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $file
     */
    public static function append(string $file, string $content, string $delimiter = '')
    {
        return rex_timer::measure(__METHOD__, static function () use ($file, $content, $delimiter) {
            if (!rex_dir::create(dirname($file)) || is_file($file) && !is_writable($file)) {
                return false;
            }

            // Check if the file exists and has content
            $hasContent = is_file($file) && filesize($file) > 0;

            // Append the content to the file with delimiter if it has existing content
            if ($hasContent) {
                $content = $delimiter . $content;
            }

            // Append the content to the file with FILE_APPEND and LOCK_EX flags
            if (false !== file_put_contents($file, $content, FILE_APPEND | LOCK_EX)) {
                @chmod($file, rex::getFilePerm());
                return true;
            }

            return false;
        });
    }

    /**
     * Puts content in a config file.
     *
     * @param string $file Path to the file
     * @param array<mixed> $content Content for the file
     * @param int $inline The level where you switch to inline YAML
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $file
     */
    public static function putConfig($file, $content, $inline = 3)
    {
        return self::put($file, rex_string::yamlEncode($content, $inline));
    }

    /**
     * Puts content in a cache file.
     *
     * @param string $file Path to the file
     * @param array<mixed> $content Content for the file
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $file
     */
    public static function putCache($file, $content)
    {
        return self::put($file, json_encode($content));
    }

    /**
     * Copies a file.
     *
     * @param string $srcfile Path of the source file
     * @param string $dstfile Path of the destination file or directory
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $srcfile
     * @psalm-assert-if-true =non-empty-string $dstfile
     */
    public static function copy($srcfile, $dstfile)
    {
        return rex_timer::measure(__METHOD__, static function () use ($srcfile, $dstfile) {
            if (is_file($srcfile)) {
                if (is_dir($dstfile)) {
                    $dstdir = rtrim($dstfile, DIRECTORY_SEPARATOR);
                    $dstfile = $dstdir . DIRECTORY_SEPARATOR . rex_path::basename($srcfile);
                } else {
                    $dstdir = dirname($dstfile);
                    rex_dir::create($dstdir);
                }

                if (rex_dir::isWritable($dstdir) && (!is_file($dstfile) || is_writable($dstfile)) && copy($srcfile, $dstfile)) {
                    @chmod($dstfile, rex::getFilePerm());
                    @touch($dstfile, filemtime($srcfile), fileatime($srcfile));
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Renames a file.
     *
     * @param string $srcfile Path of the source file
     * @param string $dstfile Path of the destination file or directory
     *
     * @return bool TRUE on success, FALSE on failure
     *
     * @psalm-assert-if-true =non-empty-string $srcfile
     * @psalm-assert-if-true =non-empty-string $dstfile
     */
    public static function move(string $srcfile, string $dstfile): bool
    {
        if (@rename($srcfile, $dstfile)) {
            return true;
        }
        if (copy($srcfile, $dstfile)) {
            return unlink($srcfile);
        }
        return false;
    }

    /**
     * Deletes a file.
     *
     * @param string $file Path of the file
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public static function delete($file)
    {
        return rex_timer::measure(__METHOD__, static function () use ($file) {
            $tryUnlink = @unlink($file);

            // re-try without error suppression to compensate possible race conditions
            if (!$tryUnlink) {
                clearstatcache(true, $file);
                if (is_file($file)) {
                    return unlink($file);
                }
            }

            return true;
        });
    }

    /**
     * Extracts the extension of the given filename.
     *
     * @param string $filename Filename
     *
     * @return string Extension of $filename
     *
     * @psalm-assert-if-true =non-empty-string $filename
     */
    public static function extension($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    /**
     * Detects the mime type of the given file.
     *
     * @param string $file Path to the file
     *
     * @return string|null Mime type or `null` if the type could not be detected
     */
    public static function mimeType($file): ?string
    {
        $mimeType = mime_content_type($file);

        if (false === $mimeType) {
            return null;
        }

        if ('text/plain' !== $mimeType) {
            // map less common types to their more common equivalent
            return match ($mimeType) {
                'application/xml' => 'text/xml',
                'image/svg' => 'image/svg+xml',
                default => $mimeType ?: null,
            };
        }

        return match (strtolower(self::extension($file))) {
            'css' => 'text/css',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'ics' => 'text/calendar',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'vcf' => 'text/vcard',
            'vtt' => 'text/vtt',
            'xml' => 'text/xml',
            default => $mimeType,
        };
    }

    /**
     * Formates the filesize of the given file into a userfriendly form.
     *
     * @param string $file Path to the file
     * @param array $format
     *
     * @return string Formatted filesize
     */
    public static function formattedSize($file, $format = [])
    {
        return rex_formatter::bytes(filesize($file), $format);
    }

    /**
     * Gets executed content of given file.
     *
     * @param string $file Path of the file
     *
     * @return string executed Content
     */
    public static function getOutput($file)
    {
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
