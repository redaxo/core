<?php

namespace Redaxo\Core;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use ReflectionClass;
use RuntimeException;

use function array_key_exists;
use function class_exists;
use function implode;
use function is_array;
use function is_dir;
use function realpath;
use function sort;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const SORT_STRING;
use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_TRAIT;
use const TOKEN_PARSE;

final class ClassDiscovery
{
    private static ?self $instance = null;

    /** @var list<string>|null */
    private ?array $relevantPaths = null;

    /** @var array<string, array<class-string, array<string, mixed>|object>>|null */
    private ?array $cacheData = null;

    private function __construct(
        private readonly ClassLoader $classLoader,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(self::findClassLoader());
    }

    /**
     * Discovers all non-abstract classes that carry the given attribute.
     *
     * @template TAttribute of object
     * @template TParent of object
     * @param class-string<TAttribute> $attributeClass
     * @param class-string<TParent>|null $parentClass Only include classes extending/implementing this type
     * @return array<class-string<TParent>, TAttribute> Map of class name to attribute instance
     */
    public function discoverByAttribute(string $attributeClass, ?string $parentClass = null): array
    {
        $cacheKey = $attributeClass . ($parentClass ? '|' . $parentClass : '');
        $cacheData = $this->loadCacheData();

        if (isset($cacheData[$cacheKey])) {
            // Reconstruct attribute instances from cached arrays (JSON roundtrip converts objects to arrays)
            $result = [];
            foreach ($cacheData[$cacheKey] as $class => $entry) {
                /**
                 * @var TAttribute $attribute
                 * @psalm-suppress MixedMethodCall
                 */
                $attribute = is_array($entry) ? new $attributeClass(...$entry) : $entry;
                $result[$class] = $attribute;
            }
            /** @var array<class-string<TParent>, TAttribute> */
            return $result;
        }

        $result = [];

        foreach ($this->getRelevantClasses() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $attributes = $reflection->getAttributes($attributeClass);

            if ([] === $attributes) {
                continue;
            }

            if (null !== $parentClass && !$reflection->isSubclassOf($parentClass)) {
                continue;
            }

            /** @var class-string<TParent> $class */
            $result[$class] = $attributes[0]->newInstance();
        }

        $this->saveCacheData($cacheKey, $result);

        return $result;
    }

    public static function clearCache(): void
    {
        File::delete(self::getCacheFile());
    }

    /** @return list<string> */
    private function getRelevantClasses(): array
    {
        $relevantPaths = $this->getRelevantPaths();
        $classes = [];

        // 1. Classes from Composer's classmap (available for all autoload types when optimized)
        foreach ($this->classLoader->getClassMap() as $class => $file) {
            $realFile = (string) realpath($file);
            if ($this->isRelevantPath($realFile, $relevantPaths)) {
                $classes[] = $class;
            }
        }

        // 2. For PSR-4 prefixes, scan directories not yet covered by the classmap.
        //    With an optimized/authoritative classmap, all classes are already in the classmap above.
        if ($this->classLoader->isClassMapAuthoritative()) {
            return $classes;
        }

        foreach ($this->classLoader->getPrefixesPsr4() as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                $realDir = (string) realpath($dir);
                if (!is_dir($realDir) || !$this->isRelevantPath($realDir . DIRECTORY_SEPARATOR, $relevantPaths)) {
                    continue;
                }

                foreach ($this->scanDirectory($realDir, $namespace) as $class) {
                    if (!array_key_exists($class, $this->classLoader->getClassMap())) {
                        $classes[] = $class;
                    }
                }
            }
        }

        return $classes;
    }

    /** @param list<string> $relevantPaths */
    private function isRelevantPath(string $path, array $relevantPaths): bool
    {
        foreach ($relevantPaths as $relevantPath) {
            if (str_starts_with($path, $relevantPath)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function getRelevantPaths(): array
    {
        if (null !== $this->relevantPaths) {
            return $this->relevantPaths;
        }

        $paths = [];

        // PSR-4 directories of the root composer package (project-level code)
        $rootPath = realpath(InstalledVersions::getRootPackage()['install_path']);
        if (false !== $rootPath) {
            $vendorPrefix = $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
            foreach ($this->classLoader->getPrefixesPsr4() as $dirs) {
                foreach ($dirs as $dir) {
                    $realDir = (string) realpath($dir);
                    if ('' !== $realDir && !str_starts_with($realDir, $vendorPrefix)) {
                        $paths[] = $realDir . DIRECTORY_SEPARATOR;
                    }
                }
            }
        }

        // Core source directory (dirname of this file's parent = src/)
        $paths[] = __DIR__ . DIRECTORY_SEPARATOR;

        // Active addon paths
        foreach (Addon::getAvailableAddons() as $addon) {
            $paths[] = $addon->path . DIRECTORY_SEPARATOR;
        }

        return $this->relevantPaths = $paths;
    }

    /** @return list<string> */
    private function scanDirectory(string $dir, string $namespace): array
    {
        $classes = [];

        /** @var RecursiveDirectoryIterator $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            if (!$this->fileDefinesClass($file->getPathname())) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($dir) + 1);
            $class = $namespace . str_replace(['/', '.php'], ['\\', ''], $relativePath);

            $classes[] = $class;
        }

        return $classes;
    }

    /** Checks whether a PHP file defines a class, interface, enum, or trait. */
    private function fileDefinesClass(string $filePath): bool
    {
        $content = file_get_contents($filePath);

        if (false === $content) {
            return false;
        }

        $tokens = token_get_all($content, TOKEN_PARSE);

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            if (T_CLASS === $token[0] || T_INTERFACE === $token[0] || T_TRAIT === $token[0] || T_ENUM === $token[0]) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<class-string, array<string, mixed>|object>> */
    private function loadCacheData(): array
    {
        if (null !== $this->cacheData) {
            return $this->cacheData;
        }

        /** @var array{addons_hash?: string, files_hash?: string, data?: array<string, array<class-string, array<string, mixed>>>} $cache */
        $cache = File::getCache(self::getCacheFile());

        if (!isset($cache['addons_hash']) || $cache['addons_hash'] !== $this->getAddonHash()) {
            return $this->cacheData = [];
        }

        // In debug mode, check if PHP files were added, removed or modified since the cache was built
        if (Core::isDebugMode()) {
            if (!isset($cache['files_hash']) || $cache['files_hash'] !== $this->getPhpFilesHash()) {
                return $this->cacheData = [];
            }
        }

        return $this->cacheData = $cache['data'] ?? [];
    }

    /** @param array<class-string, array<string, mixed>|object> $data */
    private function saveCacheData(string $cacheKey, array $data): void
    {
        $this->cacheData = $this->loadCacheData();
        $this->cacheData[$cacheKey] = $data;

        File::putCache(self::getCacheFile(), [
            'addons_hash' => $this->getAddonHash(),
            'files_hash' => $this->getPhpFilesHash(),
            'data' => $this->cacheData,
        ]);
    }

    private function getAddonHash(): string
    {
        $parts = [];
        foreach (Addon::getAvailableAddons() as $addon) {
            $parts[] = $addon->name . ':' . $addon->getVersion();
        }

        return hash('xxh128', implode(',', $parts));
    }

    /**
     * Builds a hash of all PHP files in the relevant directories, combining each file's path with its mtime.
     * Cheap — only directory listing and stat calls, no file reading — and detects added, removed or modified
     * files so the cache is invalidated automatically in debug mode.
     */
    private function getPhpFilesHash(): string
    {
        $entries = [];

        foreach ($this->getRelevantPaths() as $path) {
            if (!is_dir($path)) {
                continue;
            }

            /** @var RecursiveDirectoryIterator $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $entries[] = $file->getPathname() . ':' . (int) $file->getMTime();
                }
            }
        }

        sort($entries, SORT_STRING);

        return hash('xxh128', implode("\n", $entries));
    }

    private static function getCacheFile(): string
    {
        return Path::coreCache('class_discovery.cache');
    }

    private static function findClassLoader(): ClassLoader
    {
        return array_first(ClassLoader::getRegisteredLoaders())
            ?? throw new RuntimeException('Composer ClassLoader not found.');
    }
}
