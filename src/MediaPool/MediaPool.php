<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;

use function count;
use function in_array;
use function Redaxo\Core\View\escape;
use function strlen;

final class MediaPool
{
    /** Whether SVG files are sanitized while being added to the media pool. */
    public static bool $sanitizeSvgs = true;

    /**
     * Globally blocked file extensions (lowercase, without leading dot).
     *
     * A blocked extension must not appear as any dot-separated segment of a filename, see isAllowedExtension().
     * Extensions starting with `php` are always blocked, independently of this list.
     *
     * @var list<lowercase-string>
     */
    public static array $blockedExtensions = ['asp', 'aspx', 'bat', 'cfm', 'cgi', 'flv', 'hh', 'html', 'htaccess', 'htpasswd', 'ini', 'jsp', 'jsf', 'js', 'jsphp', 'log', 'mjs', 'pht', 'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phar', 'pl', 'ps1', 'phtml', 'py', 'rb', 'rm', 'sh', 'shmtl', 'shtml', 'swf', 'wasm', 'wmv', 'wma', 'xhtml', 'xht', 'xml'];

    /**
     * Mapping of file extensions (lowercase) to their allowed mime types, see isAllowedMimeType().
     *
     * The mapping acts as an additional allowlist on top of $blockedExtensions.
     * If empty, mime types are not checked at all.
     * The mime types must match the output of `File::mimeType()` and are compared case-sensitively.
     *
     * @var array<lowercase-string, list<lowercase-string>>
     */
    public static array $allowedMimeTypes = [
        // images
        'avif' => ['image/avif'],
        'eps' => ['application/postscript'],
        'gif' => ['image/gif'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'svg' => ['image/svg+xml'],
        'tif' => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'webp' => ['image/webp'],

        // documents
        'doc' => ['application/msword', 'application/octet-stream', 'application/encrypted'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream', 'application/encrypted'],
        'dot' => ['application/msword', 'application/octet-stream', 'application/encrypted'],
        'dotx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/octet-stream', 'application/encrypted'],
        'pdf' => ['application/pdf'],
        'pot' => ['application/vnd.ms-powerpoint'],
        'potx' => ['application/vnd.openxmlformats-officedocument.presentationml.template'],
        'pps' => ['application/vnd.ms-powerpoint'],
        'ppsx' => ['application/vnd.openxmlformats-officedocument.presentationml.slideshow'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'rtf' => ['application/rtf'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream', 'application/encrypted'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream', 'application/encrypted'],

        // text
        'csv' => ['text/csv', 'application/octet-stream'],
        'ics' => ['text/calendar'],
        'md' => ['text/markdown'],
        'srt' => ['text/plain'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'vcf' => ['text/vcard'],
        'vtt' => ['text/vtt'],

        // archives
        'gz' => ['application/x-gzip'],
        'tar' => ['application/x-tar'],
        'zip' => ['application/x-zip-compressed', 'application/zip'],

        // audio/video
        'mov' => ['video/quicktime'],
        'movie' => ['video/quicktime'],
        'mp3' => ['audio/mpeg'],
        'mp4' => ['video/mp4'],
        'mpe' => ['video/mpeg'],
        'mpeg' => ['video/mpeg'],
        'mpg' => ['video/mpeg'],
    ];

    /** Erstellt einen Filename der eindeutig ist für den Medienpool. */
    public static function filename(string $mediaName, bool $doSubindexing = true): string
    {
        // ----- neuer filename und extension holen
        $newMediaName = Str::normalize($mediaName, '_', '.-@');

        if ('.' === $newMediaName[0]) {
            $newMediaName[0] = '_';
        }

        if ($pos = strrpos($newMediaName, '.')) {
            $newMediaBaseName = substr($newMediaName, 0, strlen($newMediaName) - (strlen($newMediaName) - $pos));
            $newMediaExtension = substr($newMediaName, $pos, strlen($newMediaName) - $pos);
        } else {
            $newMediaBaseName = $newMediaName;
            $newMediaExtension = '';
        }

        // ---- ext checken - alle scriptendungen rausfiltern
        if (!self::isAllowedExtension($newMediaName)) {
            // make sure we dont add a 2nd file-extension to the file,
            // because some webspaces execute files like file.php.txt as a php script
            $newMediaBaseName .= str_replace('.', '_', $newMediaExtension);
            $newMediaExtension = '.txt';
        }

        $newMediaName = $newMediaBaseName . $newMediaExtension;

        if ($doSubindexing || $mediaName != $newMediaName) {
            // ----- datei schon vorhanden -> namen aendern -> _1 ..
            $cnt = 0;
            while (is_file(Path::media($newMediaName)) || Media::get($newMediaName)) {
                ++$cnt;
                $newMediaName = $newMediaBaseName . '_' . $cnt . $newMediaExtension;
            }
        }

        return $newMediaName;
    }

    /** @return false|string false or warning message */
    public static function mediaIsInUse(string $filename): string|false
    {
        $sql = Sql::factory();

        // FIXME move structure stuff into structure addon
        $values = [];
        for ($i = 1; $i < 21; ++$i) {
            $values[] = 'value' . $i . ' REGEXP ' . $sql->escape('(^|[^[:alnum:]+_-])' . $filename);
        }

        $files = [];
        $filelists = [];
        $escapedFilename = $sql->escape($filename);
        for ($i = 1; $i < 11; ++$i) {
            $files[] = 'media' . $i . ' = ' . $escapedFilename;
            $filelists[] = 'FIND_IN_SET(' . $escapedFilename . ', medialist' . $i . ')';
        }

        $where = '';
        $where .= implode(' OR ', $files) . ' OR ';
        $where .= implode(' OR ', $filelists) . ' OR ';
        $where .= implode(' OR ', $values);
        $query = 'SELECT DISTINCT article_id, clang_id FROM ' . Core::getTablePrefix() . 'article_slice WHERE ' . $where;

        $warning = [];
        $res = $sql->getArray($query);
        if ($sql->getRows() > 0) {
            $warning[0] = I18n::msg('pool_file_in_use_articles') . '<ul>';
            foreach ($res as $artArr) {
                $aid = (int) $artArr['article_id'];
                $clang = (int) $artArr['clang_id'];
                $article = Article::get($aid, $clang);
                $name = $article ? escape($article->name) : '';
                $warning[0] .= '<li><a href="javascript:openPage(\'' . Url::backendPage('content', ['article_id' => $aid, 'mode' => 'edit', 'clang' => $clang]) . '\')">' . $name . '</a></li>';
            }
            $warning[0] .= '</ul>';
        }

        // ----- EXTENSION POINT
        $warning = Extension::dispatch(new ExtensionPoint('MEDIA_IS_IN_USE', $warning, [
            'filename' => $filename,
        ]));

        if (!empty($warning)) {
            return '<br /><br />' . implode('', $warning);
        }

        return false;
    }

    /**
     * Check if the media type (extension) is allowed for upload.
     *
     * @param list<string> $types Restrict to these file extensions (in addition to the global block list)
     */
    public static function isAllowedExtension(string $filename, array $types = []): bool
    {
        $fileExt = mb_strtolower(File::extension($filename));

        if ('' === $filename || str_contains($fileExt, ' ') || '' === $fileExt) {
            return false;
        }

        if (str_starts_with($fileExt, 'php')) {
            return false;
        }

        // A blocked extension must not appear as a dot-separated segment anywhere in the filename,
        // to prevent double/multi extension vulnerabilities: some webspaces execute a file named
        // e.g. `foo.php.txt` or `foo.php.any.jpg` as php. Matching whole segments (instead of a
        // substring) avoids false positives like `foo.json` (contains `.js`) or `js_datei.txt`.
        foreach (explode('.', mb_strtolower($filename)) as $segment) {
            if (in_array($segment, self::$blockedExtensions, true)) {
                return false;
            }
        }

        $allowedExtensions = self::getAllowedExtensions($types);
        return !count($allowedExtensions) || in_array($fileExt, $allowedExtensions);
    }

    /**
     * Checks file against the optional allowlist in `self::$allowedMimeTypes`.
     *
     * @param string $path Path to the physical file
     * @param string|null $filename Optional filename, will be used for extracting the file extension.
     *                              If not given, the extension is extracted from `$path`.
     */
    public static function isAllowedMimeType(string $path, ?string $filename = null): bool
    {
        $allowedMimetypes = self::$allowedMimeTypes;

        if (!$allowedMimetypes) {
            return true;
        }

        $extension = mb_strtolower(File::extension($filename ?: $path));

        if (!isset($allowedMimetypes[$extension])) {
            return false;
        }

        $mimeType = File::mimeType($path, $filename);

        return in_array($mimeType, $allowedMimetypes[$extension]);
    }

    /**
     * Get allowed media type extensions given via media widget "types" param.
     *
     * @param list<string> $types
     * @return list<string> allowed extensions
     */
    public static function getAllowedExtensions(array $types = []): array
    {
        $allowedExtensions = [];
        foreach ($types as $ext) {
            $ext = ltrim($ext, '.');
            $ext = mb_strtolower($ext);
            if (!in_array($ext, self::$blockedExtensions)) { // allowedExtensions cannot override any blockedExtensions entry from master
                $allowedExtensions[] = $ext;
            }
        }
        return $allowedExtensions;
    }
}
