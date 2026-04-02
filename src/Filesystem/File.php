<?php

namespace Redaxo\Core\Filesystem;

use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Util\Exception\YamlParseException;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;

use function dirname;

use const DIRECTORY_SEPARATOR;
use const FILE_APPEND;
use const LOCK_EX;
use const PATHINFO_EXTENSION;

/**
 * Class for handling files.
 */
final class File
{
    private function __construct() {}

    /**
     * Returns the content of a file.
     *
     * @param string $file Path to the file
     * @return string Content of the file
     *
     * @psalm-assert non-empty-string $file
     */
    public static function require(string $file): string
    {
        return Timer::measure(__METHOD__, static function () use ($file): string {
            $content = @file_get_contents($file);

            if (false === $content) {
                throw new RuntimeException('Unable to read file "' . $file . '".');
            }

            return $content;
        });
    }

    /**
     * Returns the content of a file.
     *
     * @param string $file Path to the file
     * @param string|null $default Default value
     * @return string|null Content of the file or default value if the file isn't readable
     * @psalm-return ($default is null ? string|null : string)
     */
    public static function get(string $file, ?string $default = null): ?string
    {
        return Timer::measure(__METHOD__, static function () use ($file, $default): ?string {
            $content = @file_get_contents($file);
            return false !== $content ? $content : $default;
        });
    }

    /**
     * Returns the content of a config file.
     *
     * @param string $file Path to the file
     * @param array<mixed>|null $default Default value
     * @throws YamlParseException
     * @return array<mixed>|null Content of the file or default value if the file isn't readable
     * @psalm-return ($default is null ? array<mixed>|null : array<mixed>)
     */
    public static function getConfig(string $file, ?array $default = []): ?array
    {
        $content = self::get($file);
        return null === $content ? $default : Str::yamlDecode($content);
    }

    /**
     * Returns the content of a cache file.
     *
     * @param string $file Path to the file
     * @param array<mixed>|null $default Default value
     * @return array<mixed>|null Content of the file or default value if the file isn't readable
     * @psalm-return ($default is null ? array<mixed>|null : array<mixed>)
     */
    public static function getCache(string $file, ?array $default = []): ?array
    {
        $content = self::get($file);
        return null === $content ? $default : Type::array(json_decode($content, true));
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
    public static function put(string $file, string $content): bool
    {
        return Timer::measure(__METHOD__, static function () use ($file, $content): bool {
            if (!Dir::create(dirname($file)) || is_file($file) && !is_writable($file)) {
                return false;
            }

            // mimic a atomic write
            $tmpFile = @tempnam(dirname($file), Path::basename($file));
            if (false !== file_put_contents($tmpFile, $content) && self::move($tmpFile, $file)) {
                @chmod($file, Core::getFilePerm());
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
    public static function append(string $file, string $content, string $delimiter = ''): bool
    {
        return Timer::measure(__METHOD__, static function () use ($file, $content, $delimiter): bool {
            if (!Dir::create(dirname($file)) || is_file($file) && !is_writable($file)) {
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
                @chmod($file, Core::getFilePerm());
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
    public static function putConfig(string $file, array $content, int $inline = 3): bool
    {
        return self::put($file, Str::yamlEncode($content, $inline));
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
    public static function putCache(string $file, array $content): bool
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
    public static function copy(string $srcfile, string $dstfile): bool
    {
        return Timer::measure(__METHOD__, static function () use ($srcfile, $dstfile): bool {
            if (is_file($srcfile)) {
                if (is_dir($dstfile)) {
                    $dstdir = rtrim($dstfile, DIRECTORY_SEPARATOR);
                    $dstfile = $dstdir . DIRECTORY_SEPARATOR . Path::basename($srcfile);
                } else {
                    $dstdir = dirname($dstfile);
                    Dir::create($dstdir);
                }

                if (Dir::isWritable($dstdir) && (!is_file($dstfile) || is_writable($dstfile)) && copy($srcfile, $dstfile)) {
                    @chmod($dstfile, Core::getFilePerm());
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
    public static function delete(string $file): bool
    {
        return Timer::measure(__METHOD__, static function () use ($file): bool {
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
    public static function extension(string $filename): string
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
    public static function mimeType(string $file): ?string
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
            'md' => 'text/markdown',
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
     * @param array{0?: int, 1?: string, 2?: string} $format
     *
     * @return string Formatted filesize
     */
    public static function formattedSize(string $file, array $format = []): string
    {
        return Formatter::bytes(filesize($file), $format);
    }

    /**
     * Gets executed content of given file.
     *
     * @param string $file Path of the file
     *
     * @return string executed Content
     */
    public static function getOutput(string $file): string
    {
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
