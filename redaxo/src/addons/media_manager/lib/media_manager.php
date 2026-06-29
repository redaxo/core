<?php

/**
 * @package redaxo\media-manager
 */
class rex_media_manager
{
    /**
     * status of a system mediatyp.
     *
     * @var int
     */
    public const STATUS_SYSTEM_TYPE = 1;

    /** @var rex_managed_media */
    private $media;

    /** @var string */
    private $originalFilename;

    /** @var string|null */
    private $cachePath;

    /** @var string|null */
    private $type;

    /** @var bool */
    private $useCache = true;

    /** @var array{media_path: ?string, media_filename: string, format: string, headers: array<string, string>}|null */
    private $cache;

    /** @var bool */
    private $notFound = false;

    /** @var string|null */
    private static $cacheDirectory;

    /** @var list<class-string<rex_effect_abstract>> */
    private static $effects = [];

    public function __construct(rex_managed_media $media)
    {
        $this->media = $media;
        $this->originalFilename = $media->getMediaFilename();
    }

    /**
     * Creates a rex_managed_media object for the given file and mediatype.
     * This object might be used to determine the dimension of a image or similar.
     *
     * @param string $type Media type
     * @param string $file Media file
     *
     * @return self
     */
    public static function create($type, $file)
    {
        $mediaPath = rex_path::media($file);
        $cachePath = rex_path::addonCache('media_manager');

        $media = new rex_managed_media($mediaPath);
        $manager = new self($media);
        $manager->setCachePath($cachePath);
        $manager->applyEffects($type);

        if (!$manager->isCached() && $manager->useCache && !$manager->notFound) {
            $media->save($manager->getCacheFilename(), $manager->getHeaderCacheFilename());
        }

        $media->refreshImageDimensions();

        return $manager;
    }

    /** @return rex_managed_media */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * @param string $type
     * @return void
     */
    protected function applyEffects($type)
    {
        $this->type = $type;

        rex_extension::registerPoint(new rex_extension_point('MEDIA_MANAGER_INIT', $this, [
            'type' => $type,
            'file' => $this->originalFilename,
        ], true));

        if (!$this->isCached()) {
            $set = $this->effectsFromType($type);
            $set = rex_extension::registerPoint(new rex_extension_point('MEDIA_MANAGER_FILTERSET', $set, ['rex_media_type' => $type]));

            if (0 == count($set)) {
                $this->useCache = false;
                $this->notFound = !$this->media->exists();

                return;
            }

            // execute effects on image
            foreach ($set as $effectParams) {
                /** @var class-string<rex_effect_abstract> $effectClass */
                $effectClass = 'rex_effect_' . $effectParams['effect'];
                /**
                 * @var rex_effect_abstract $effect
                 * @psalm-ignore-var
                 */
                $effect = new $effectClass();
                $effect->setMedia($this->media);
                $effect->setParams($effectParams['params']);

                try {
                    $effect->execute();
                } catch (rex_media_manager_not_found_exception) {
                    $this->notFound = true;

                    return;
                }
            }

            $this->notFound = !$this->media->exists();
        }

        if ($this->useCache && $this->isCached()) {
            $cache = $this->getHeaderCache();
            assert(null !== $cache);

            $this->media->setMediaPath($cache['media_path']);
            $this->media->setMediaFilename($cache['media_filename']);
            $this->media->setFormat($cache['format']);

            // must be called after setMediaPath, because setMediaPath overwrites sourcePath, too
            $this->media->setSourcePath($this->getCacheFilename());

            foreach ($cache['headers'] as $key => $value) {
                $this->media->setHeader($key, $value);
            }
        }
    }

    /**
     * @param string $type
     * @return list<array{effect: string, params: array<string, mixed>}>
     */
    public function effectsFromType($type)
    {
        $qry = '
            SELECT e.*
            FROM ' . rex::getTablePrefix() . 'media_manager_type t, ' . rex::getTablePrefix() . 'media_manager_type_effect e
            WHERE e.type_id = t.id AND t.name=? order by e.priority';

        $sql = rex_sql::factory();
        // $sql->setDebug();
        $sql->setQuery($qry, [$type]);

        $effects = [];
        foreach ($sql as $row) {
            $effname = (string) $row->getValue('effect');
            /** @var array<string, array<string, mixed>> $params */
            $params = $row->getArrayValue('parameters');
            $effparams = [];

            // extract parameter out of array
            if (isset($params['rex_effect_' . $effname])) {
                foreach ($params['rex_effect_' . $effname] as $name => $value) {
                    $effparams[str_replace('rex_effect_' . $effname . '_', '', $name)] = $value;
                    unset($effparams[$name]);
                }
            }

            $effect = [
                'effect' => $effname,
                'params' => $effparams,
            ];

            $effects[] = $effect;
        }

        return $effects;
    }

    /** Set base cache directory for generated images. */
    public static function setCacheDirectory(string $path): void
    {
        self::$cacheDirectory = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $path
     * @return void
     */
    public function setCachePath($path = '')
    {
        $this->cachePath = $path;
    }

    /** @return string|null */
    public function getCachePath()
    {
        return $this->cachePath;
    }

    /**
     * @param bool $useCache
     * @return void
     */
    protected function useCache($useCache = true)
    {
        $this->useCache = $useCache;
    }

    /** @return bool */
    public function isCached()
    {
        $cacheFile = $this->getCacheFilename();

        if (!is_file($cacheFile)) {
            return false;
        }

        $cache = $this->getHeaderCache();

        if (!$cache) {
            return false;
        }

        $mediapath = $cache['media_path'];

        if (null === $mediapath) {
            return true;
        }

        if (!is_file($mediapath)) {
            return false;
        }

        $cachetime = filemtime($cacheFile);
        $filetime = filemtime($mediapath);

        // cache is newer?
        return $cachetime >= $filetime;
    }

    /** @return string */
    public function getCacheFilename()
    {
        assert(null !== $this->cachePath);
        assert(null !== $this->type);
        return $this->cachePath . $this->type . '/' . $this->originalFilename;
    }

    /** @return string */
    public function getHeaderCacheFilename()
    {
        return $this->getCacheFilename() . '.header';
    }

    /** @return array{media_path: ?string, media_filename: string, format: string, headers: array<string, string>}|null */
    private function getHeaderCache()
    {
        if ($this->cache) {
            return $this->cache;
        }

        /** @var array{media_path: ?string, media_filename: string, format: string, headers: array<string, string>}|null $cache */
        $cache = rex_file::getCache($this->getHeaderCacheFilename(), null);

        return $this->cache = $cache;
    }

    /**
     * @param int $typeId
     * @return int
     */
    public static function deleteCacheByType($typeId)
    {
        $qry = 'SELECT * FROM ' . rex::getTablePrefix() . 'media_manager_type WHERE id=?';
        $sql = rex_sql::factory();
        //  $sql->setDebug();
        $sql->setQuery($qry, [$typeId]);
        $counter = 0;
        foreach ($sql as $row) {
            $counter += self::deleteCache(null, (string) $row->getValue('name'));
        }

        rex_file::delete(rex_path::addonCache('media_manager', 'types.cache'));

        return $counter;
    }

    /**
     * @param string|null $filename
     * @param string|null $type
     * @return int
     */
    public static function deleteCache($filename = null, $type = null)
    {
        if (null === $filename) {
            rex_file::delete(rex_path::addonCache('media_manager', 'types.cache'));
        }

        $filename = ($filename ?: '') . '*';

        if (!$type) {
            $type = '*';
        }

        $counter = 0;
        $folder = self::$cacheDirectory ?? rex_path::addonCache('media_manager');

        $glob = glob($folder . $type . '/' . $filename, GLOB_NOSORT);
        if ($glob) {
            foreach ($glob as $file) {
                if (rex_file::delete($file)) {
                    ++$counter;
                }
            }
        }

        return $counter;
    }

    /** @return never */
    public function sendMedia()
    {
        rex_extension::registerPoint(new rex_extension_point('MEDIA_MANAGER_BEFORE_SEND', $this, []));

        rex_response::cleanOutputBuffers();

        if ($this->notFound) {
            header('HTTP/1.1 ' . rex_response::HTTP_NOT_FOUND);

            exit;
        }

        // check for a cache-buster. this needs to be done, before the session gets closed/aborted.
        // the header is sent directly, to make sure it gets not cached with the other media related headers.
        if (rex_get('buster')) {
            if (PHP_SESSION_ACTIVE == session_status()) {
                // short lived cache, for resources which might be affected by e.g. permissions
                rex_response::sendCacheControl('private, max-age=7200');
            } else {
                rex_response::sendCacheControl('public, max-age=31536000, immutable');
            }
        }

        // prevent session locking trough other addons
        session_abort();

        $headerCacheFilename = $this->getHeaderCacheFilename();
        $CacheFilename = $this->getCacheFilename();

        if ($this->useCache && $this->isCached()) {
            $cache = $this->getHeaderCache();
            assert(null !== $cache);
            $header = $cache['headers'];
            if (isset($header['Last-Modified'])) {
                rex_response::sendLastModified(strtotime($header['Last-Modified']));
                unset($header['Last-Modified']);
            }
            foreach ($header as $t => $c) {
                rex_response::setHeader($t, $c);
            }
            rex_response::sendFile($CacheFilename, $header['Content-Type']);
        } else {
            $this->media->sendMedia($CacheFilename, $headerCacheFilename, $this->useCache);
        }

        rex_extension::registerPoint(new rex_extension_point('MEDIA_MANAGER_AFTER_SEND', $this, []));

        exit;
    }

    /** @return array<class-string<rex_effect_abstract>, string> */
    public static function getSupportedEffects()
    {
        $dirs = [
            __DIR__ . '/effects/',
        ];

        $effects = [];
        foreach ($dirs as $dir) {
            $files = glob($dir . 'effect_*.php');
            if ($files) {
                foreach ($files as $file) {
                    $effects[self::getEffectClass($file)] = self::getEffectName($file);
                }
            }
        }

        foreach (self::$effects as $class) {
            $effects[$class] = str_replace(['rex_', 'effect_'], '', $class);
        }

        return $effects;
    }

    /**
     * @param class-string<rex_effect_abstract> $class
     * @return void
     */
    public static function addEffect($class)
    {
        self::$effects[] = $class;
    }

    private static function getEffectName(string $effectFile): string
    {
        return str_replace(
            ['effect_', '.php'],
            '',
            rex_path::basename($effectFile),
        );
    }

    /** @return class-string<rex_effect_abstract> */
    private static function getEffectClass(string $effectFile): string
    {
        /** @var class-string<rex_effect_abstract> */
        return 'rex_' . str_replace(
            '.php',
            '',
            rex_path::basename($effectFile),
        );
    }

    /*
     * For ExtensionPoints.
     */

    /**
     * Checks if media is used by this addon.
     * @return list<string> Warning message as array
     */
    public static function mediaIsInUse(rex_extension_point $ep)
    {
        /** @var list<string> $warning */
        $warning = $ep->getSubject();
        $filename = $ep->getParam('filename');
        assert(is_string($filename));

        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT DISTINCT effect.id AS effect_id, effect.type_id, type.id, type.name
            FROM `' . rex::getTable('media_manager_type_effect') . '` AS effect
            LEFT JOIN `' . rex::getTable('media_manager_type') . '` AS type ON effect.type_id = type.id
            WHERE parameters LIKE ?
        ', ['%' . $sql->escapeLikeWildcards(json_encode($filename)) . '%']);

        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $message = '<a href="javascript:openPage(\'' . rex_url::backendPage('media_manager/types', ['effects' => 1, 'type_id' => $sql->getValue('type_id'), 'effect_id' => $sql->getValue('effect_id'), 'func' => 'edit']) . '\')">' . rex_i18n::msg('media_manager') . ' ' . rex_i18n::msg('media_manager_effect_name') . ': ' . rex_escape((string) $sql->getValue('name')) . '</a>';

            if (!in_array($message, $warning)) {
                $warning[] = $message;
            }
        }

        return $warning;
    }

    /** @return void */
    public static function mediaUpdated(rex_extension_point $ep)
    {
        self::deleteCache((string) $ep->getParam('filename'));
    }

    /** @return void */
    public static function init()
    {
        // --- handle image request
        $rexMediaManagerFile = self::getMediaFile();
        $rexMediaManagerType = self::getMediaType();

        if ('' != $rexMediaManagerFile && '' != $rexMediaManagerType) {
            $mediaPath = rex_path::media($rexMediaManagerFile);
            $cachePath = self::$cacheDirectory ?? rex_path::addonCache('media_manager');

            $media = new rex_managed_media($mediaPath);
            $mediaManager = new self($media);
            $mediaManager->setCachePath($cachePath);
            $mediaManager->applyEffects($rexMediaManagerType);
            $mediaManager->sendMedia();
        }
    }

    /** @return string */
    public static function getMediaFile()
    {
        return rex_path::basename(rex_get('rex_media_file', 'string'));
    }

    /** @return string */
    public static function getMediaType()
    {
        $type = rex_get('rex_media_type', 'string');

        // map deprecated types to new types
        $deprecatedTypesMap = [
            'rex_mediapool_detail' => 'rex_media_medium',
            'rex_mediapool_maximized' => 'rex_media_large',
            'rex_mediapool_preview' => 'rex_media_small',
            'rex_mediabutton_preview' => 'rex_media_medium',
            'rex_medialistbutton_preview' => 'rex_media_medium',
        ];
        if (array_key_exists($type, $deprecatedTypesMap)) {
            $type = $deprecatedTypesMap[$type];
        }

        return rex_path::basename($type);
    }

    /**
     * @param string $type Media type
     * @param string|rex_media $file Media file
     * @param int|null $timestamp Last change timestamp of given file, for cache buster parameter
     *                            (not nessary when the file is given by a `rex_media` object)
     * @param bool $escape
     *
     * @return string
     */
    public static function getUrl($type, $file, $timestamp = null, $escape = true)
    {
        if ($file instanceof rex_media) {
            if (null === $timestamp) {
                $timestamp = $file->getUpdateDate();
            }

            $file = $file->getFileName();
        }

        $params = [
            'rex_media_type' => $type,
            'rex_media_file' => $file,
        ];

        if (null !== $timestamp) {
            $cache = self::getTypeCache();

            if (isset($cache[$type])) {
                $params['buster'] = max($timestamp, $cache[$type]);
            }
        }

        $url = rex_url::frontendController($params, $escape);

        return rex_extension::registerPoint(new rex_extension_point('MEDIA_MANAGER_URL', $url, [
            'type' => $type,
            'file' => $file,
            'buster' => $params['buster'] ?? null,
            'escape' => $escape,
        ]));
    }

    /** @return array<string, int> */
    private static function getTypeCache(): array
    {
        $file = rex_path::addonCache('media_manager', 'types.cache');

        /** @var array<string, int>|null $cache */
        $cache = rex_file::getCache($file, null);

        if (null !== $cache) {
            return $cache;
        }

        $cache = [];

        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name, updatedate FROM ' . rex::getTable('media_manager_type'));

        foreach ($sql as $row) {
            $cache[(string) $row->getValue('name')] = (int) $row->getDateTimeValue('updatedate');
        }

        rex_file::putCache($file, $cache);

        return $cache;
    }
}
