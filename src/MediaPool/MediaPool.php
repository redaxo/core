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
use function strlen;

final class MediaPool
{
    /**
     * Erstellt einen Filename der eindeutig ist für den Medienpool.
     *
     * @param string $mediaName Dateiname
     * @param bool $doSubindexing
     */
    public static function filename(string $mediaName, $doSubindexing = true): string
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

    /** @return bool|string */
    public static function mediaIsInUse(string $filename)
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
                $ooa = Article::get($aid, $clang);
                $name = ($ooa) ? $ooa->getName() : '';
                $warning[0] .= '<li><a href="javascript:openPage(\'' . Url::backendPage('content', ['article_id' => $aid, 'mode' => 'edit', 'clang' => $clang]) . '\')">' . $name . '</a></li>';
            }
            $warning[0] .= '</ul>';
        }

        // ----- EXTENSION POINT
        $warning = Extension::registerPoint(new ExtensionPoint('MEDIA_IS_IN_USE', $warning, [
            'filename' => $filename,
        ]));

        if (!empty($warning)) {
            return '<br /><br />' . implode('', $warning);
        }

        return false;
    }

    /** check if mediatpye(extension) is allowed for upload. */
    public static function isAllowedExtension(string $filename, array $args = []): bool
    {
        $fileExt = mb_strtolower(File::extension($filename));

        if ('' === $filename || str_contains($fileExt, ' ') || '' === $fileExt) {
            return false;
        }

        if (str_starts_with($fileExt, 'php')) {
            return false;
        }

        $blockedExtensions = self::getBlockedExtensions();
        foreach ($blockedExtensions as $blockedExtension) {
            // $blockedExtensions extensions are not allowed within filenames, to prevent double extension vulnerabilities:
            // -> some webspaces execute files named file.php.txt as php
            if (str_ends_with($filename, '.' . $blockedExtension) // Prüfe ob der String mit der verbotenen Endung endet
                || str_ends_with($filename, '.' . $blockedExtension . '.' . $fileExt) // prüfe ob es keine doppelte Endung der Form *.php.ext gibt
            ) {
                return false;
            }
        }

        $allowedExtensions = self::getAllowedExtensions($args);
        return !count($allowedExtensions) || in_array($fileExt, $allowedExtensions);
    }

    /**
     * Checks file against optional property `allowed_mime_types`.
     *
     * @param string $path Path to the physical file
     * @param string|null $filename Optional filename, will be used for extracting the file extension.
     *                              If not given, the extension is extracted from `$path`.
     */
    public static function isAllowedMimeType(string $path, ?string $filename = null): bool
    {
        $allowedMimetypes = self::getAllowedMimeTypes();

        if (!$allowedMimetypes) {
            return true;
        }

        $extension = mb_strtolower(File::extension($filename ?: $path));

        if (!isset($allowedMimetypes[$extension])) {
            return false;
        }

        $mimeType = File::mimeType($path);

        return in_array($mimeType, $allowedMimetypes[$extension]);
    }

    /**
     * Get allowed mediatype extensions given via media widget "types" param.
     *
     * @param array $args widget params
     *
     * @return array allowedExtensions
     */
    public static function getAllowedExtensions(array $args = []): array
    {
        $blockedExtensions = self::getBlockedExtensions();

        $allowedExtensions = [];
        if (isset($args['types'])) {
            foreach (explode(',', $args['types']) as $ext) {
                $ext = ltrim($ext, '.');
                $ext = mb_strtolower($ext);
                if (!in_array($ext, $blockedExtensions)) { // allowedExtensions cannot override any blockedExtensions entry from master
                    $allowedExtensions[] = $ext;
                }
            }
        }
        return $allowedExtensions;
    }

    /**
     * Get global blocked mediatype extensions.
     *
     * @return array blocked mediatype extensions
     */
    public static function getBlockedExtensions(): array
    {
        return Core::getProperty('blocked_extensions', []);
    }

    /**
     * Get global list of allowed mime types.
     *
     * @return array<string, list<string>> Mapping of file extensions to corresponding list of allowed mime types
     */
    public static function getAllowedMimeTypes(): array
    {
        return Core::getProperty('allowed_mime_types', []);
    }

    /**
     * Set global list of allowed mime types.
     *
     * @param array<string, list<string>> $mimeTypes Mapping of file extensions to corresponding list of allowed mime types
     */
    public static function setAllowedMimeTypes(array $mimeTypes): void
    {
        Core::setProperty('allowed_mime_types', $mimeTypes);
    }
}
