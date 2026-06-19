<?php

namespace Redaxo\Core\MediaManager;

use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaManager\Attribute\AsMediaType;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\MediaPool\Media;

use function assert;
use function filemtime;
use function glob;
use function hash;
use function is_file;
use function rtrim;
use function session_abort;
use function session_status;
use function substr;

use const DIRECTORY_SEPARATOR;
use const GLOB_NOSORT;
use const PHP_SESSION_ACTIVE;

/**
 * Front controller for delivering media files transformed by a {@see MediaType}.
 *
 * A media manager URL (`?rex_media_type=...&rex_media_file=...`) is handled in {@see self::init()}:
 * the registered type processes the file via {@see MediaProcessor}, the result is cached under a
 * content hash and delivered. Types are code-registered via
 * {@see AsMediaType}.
 */
final class MediaManager
{
    private static ?string $cacheDirectory = null;

    /** Set the base cache directory for generated files. */
    public static function setCacheDirectory(string $path): void
    {
        self::$cacheDirectory = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /** @internal */
    #[AsExtension('PACKAGES_INCLUDED', ExtensionLevel::Early)]
    public static function init(): void
    {
        $file = self::getMediaFile();
        $type = self::getMediaType();

        if ('' === $file || '' === $type || !MediaTypeRegistry::has($type)) {
            return;
        }

        self::handle($type, $file);
    }

    /** @internal */
    public static function getMediaFile(): string
    {
        return Path::basename(Request::get('rex_media_file', 'string'));
    }

    /** @internal */
    public static function getMediaType(): string
    {
        return Path::basename(Request::get('rex_media_type', 'string'));
    }

    /**
     * Builds the frontend URL that delivers the given file through the given media type.
     *
     * @param string $type Media type
     * @param string|Media $file Media file (a {@see Media} object provides its own change timestamp)
     * @param int|null $timestamp Last change timestamp of the file, used for the cache-buster
     *                            (not necessary when the file is given by a {@see Media} object)
     * @return string
     */
    public static function getUrl($type, $file, $timestamp = null)
    {
        if ($file instanceof Media) {
            if (null === $timestamp) {
                $timestamp = $file->updateDate;
            }

            $file = $file->fileName;
        }

        $params = [
            'rex_media_type' => $type,
            'rex_media_file' => $file,
        ];

        // cache-buster: changes whenever the media file or the type definition changes
        $sourceHash = MediaTypeRegistry::sourceHash($type);
        if ('' !== $sourceHash) {
            $params['buster'] = substr(hash('xxh128', $sourceHash . '|' . ($timestamp ?? 0)), 0, 12);
        }

        $url = Url::frontendController($params);

        return Extension::dispatch(new ExtensionPoint('MEDIA_MANAGER_URL', $url, [
            'type' => $type,
            'file' => $file,
            'buster' => $params['buster'] ?? null,
        ]));
    }

    /**
     * Deletes cached files, optionally limited to a single media filename.
     *
     * @return int Number of deleted files
     */
    public static function deleteCache(?string $filename = null): int
    {
        $base = self::$cacheDirectory ?? Path::coreCache('media_manager/');

        // cache layout: {type}/{hash}/{filename}
        $pattern = $base . '*/*/' . ($filename ?? '') . '*';

        $counter = 0;
        foreach (glob($pattern, GLOB_NOSORT) ?: [] as $file) {
            if (File::delete($file)) {
                ++$counter;
            }
        }

        return $counter;
    }

    /**
     * @return void
     *
     * @internal
     */
    #[AsExtension('MEDIA_UPDATED')]
    #[AsExtension('MEDIA_DELETED')]
    public static function mediaUpdated(ExtensionPoint $ep)
    {
        self::deleteCache((string) $ep->getParam('filename'));
    }

    /** @return never */
    private static function handle(string $typeName, string $file): void
    {
        $type = MediaTypeRegistry::get($typeName);
        assert(null !== $type);

        // content negotiation (resolved before processing so cached variants need no re-processing)
        $negotiatedFormat = null;
        if ($type instanceof NegotiatesFormat) {
            $accept = Request::server('HTTP_ACCEPT', 'string', '');
            $negotiatedFormat = FormatNegotiator::negotiate($type->negotiableFormats(), $accept);
        }

        $variant = null === $negotiatedFormat ? '' : $negotiatedFormat->name;

        $sourcePath = Path::media($file);
        $cacheFile = self::typeCacheFile($typeName, $file, $sourcePath, $variant);
        $metaFile = $cacheFile . '.meta';

        Response::cleanOutputBuffers();

        // prevent session locking through other addons
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_abort();
        }

        /** @var array{mediaType: string, download: bool, downloadFilename: string, cacheControl: string|null, headers: array<string, string>}|null $meta */
        $meta = is_file($cacheFile) ? File::getCache($metaFile, null) : null;

        if (null === $meta) {
            try {
                $result = new MediaProcessor(ImageManagerFactory::create())->render($type, $sourcePath, $file, $negotiatedFormat);
            } catch (MediaNotFoundException) {
                header('HTTP/1.1 ' . Response::HTTP_NOT_FOUND);

                exit;
            }

            if ($result->isRaw()) {
                assert(null !== $result->sourcePath);
                File::copy($result->sourcePath, $cacheFile);
            } else {
                assert(null !== $result->content);
                File::put($cacheFile, $result->content);
            }

            $meta = $result->meta();
            if ($type instanceof NegotiatesFormat) {
                // the response depends on the Accept header -> let browsers and CDNs cache per format
                $meta['headers']['Vary'] = 'Accept';
            }

            File::putCache($metaFile, $meta);
        }

        self::sendFromCache($cacheFile, $meta);
    }

    /**
     * @param array{mediaType: string, download: bool, downloadFilename: string, cacheControl: string|null, headers: array<string, string>} $meta
     * @return never
     */
    private static function sendFromCache(string $cacheFile, array $meta): void
    {
        // cache-buster present -> long-lived immutable caching (sent before any other cache-control)
        if (Request::get('buster')) {
            Response::sendCacheControl('public, max-age=31536000, immutable');
        } elseif (null !== $meta['cacheControl']) {
            Response::sendCacheControl($meta['cacheControl']);
        }

        foreach ($meta['headers'] as $name => $value) {
            Response::setHeader($name, $value);
        }

        Response::sendFile($cacheFile, $meta['mediaType'], $meta['download'] ? 'attachment' : 'inline', $meta['downloadFilename']);

        exit;
    }

    /**
     * Cache file path; the key embeds the type's source hash, the media's mtime and the variant
     * (e.g. negotiated format), so edits and per-request variants invalidate/separate automatically.
     */
    private static function typeCacheFile(string $typeName, string $file, string $sourcePath, string $variant = ''): string
    {
        $base = self::$cacheDirectory ?? Path::coreCache('media_manager/');
        $mtime = is_file($sourcePath) ? (int) filemtime($sourcePath) : 0;
        $key = hash('xxh128', MediaTypeRegistry::sourceHash($typeName) . '|' . $mtime . '|' . $variant);

        return $base . $typeName . '/' . $key . '/' . $file;
    }
}
