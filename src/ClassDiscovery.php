<?php

namespace Redaxo\Core;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

use function array_key_exists;
use function class_exists;
use function function_exists;
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

    /** @var array<string, mixed>|null */
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

        if (!isset($cacheData[$cacheKey])) {
            // Only the attribute arguments are cached (always constant expressions, hence var_export-safe);
            // the instance is reconstructed below from the known attribute class.
            $raw = [];

            foreach ($this->getReflections() as $reflection) {
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

                $raw[$reflection->getName()] = $attributes[0]->getArguments();
            }

            $this->saveCacheData($cacheKey, $raw);
            $cacheData[$cacheKey] = $raw;
        }

        /** @var array<class-string<TParent>, array<int|string, mixed>> $cached */
        $cached = $cacheData[$cacheKey];

        $result = [];
        foreach ($cached as $class => $arguments) {
            /**
             * @var TAttribute $attribute
             * @psalm-suppress MixedMethodCall
             */
            $attribute = new $attributeClass(...$arguments);
            $result[$class] = $attribute;
        }

        /** @var array<class-string<TParent>, TAttribute> */
        return $result;
    }

    /**
     * Discovers all non-abstract methods (static and instance) that carry the given attribute.
     *
     * Each entry contains the declaring class, method name, whether the method is static, the class/interface types of
     * the method's first parameter (a list, so union types are fully captured), and the attribute instance. Methods
     * inherited from parent classes are not duplicated — only the declaring class is reported.
     *
     * @template TAttribute of object
     * @param class-string<TAttribute> $attributeClass
     * @return list<array{class: class-string, method: string, isStatic: bool, firstParameterTypes: list<class-string>, attribute: TAttribute}>
     */
    public function discoverByMethodAttribute(string $attributeClass): array
    {
        $cacheKey = 'method:' . $attributeClass;
        $cacheData = $this->loadCacheData();

        if (!isset($cacheData[$cacheKey])) {
            // Only the attribute arguments are cached (always constant expressions, hence var_export-safe);
            // the instance is reconstructed below from the known attribute class.
            $raw = [];

            foreach ($this->getReflections() as $reflection) {
                $class = $reflection->getName();

                foreach ($reflection->getMethods() as $method) {
                    if ($method->isAbstract()) {
                        continue;
                    }

                    // Skip methods inherited from a parent — they'll be reported on their declaring class.
                    if ($method->getDeclaringClass()->getName() !== $class) {
                        continue;
                    }

                    $attributes = $method->getAttributes($attributeClass);
                    if ([] === $attributes) {
                        continue;
                    }

                    $firstParameterTypes = self::classTypes(($method->getParameters()[0] ?? null)?->getType());

                    foreach ($attributes as $attribute) {
                        $raw[] = [
                            'class' => $class,
                            'method' => $method->getName(),
                            'isStatic' => $method->isStatic(),
                            'firstParameterTypes' => $firstParameterTypes,
                            'arguments' => $attribute->getArguments(),
                        ];
                    }
                }
            }

            $this->saveCacheData($cacheKey, $raw);
            $cacheData[$cacheKey] = $raw;
        }

        /** @var list<array{class: class-string, method: string, isStatic: bool, firstParameterTypes: list<class-string>, arguments: array<int|string, mixed>}> $cached */
        $cached = $cacheData[$cacheKey];

        $result = [];
        foreach ($cached as $entry) {
            /**
             * @var TAttribute $attribute
             * @psalm-suppress MixedMethodCall
             */
            $attribute = new $attributeClass(...$entry['arguments']);
            $result[] = [
                'class' => $entry['class'],
                'method' => $entry['method'],
                'isStatic' => $entry['isStatic'],
                'firstParameterTypes' => $entry['firstParameterTypes'],
                'attribute' => $attribute,
            ];
        }

        return $result;
    }

    /**
     * Returns the class/interface type names of a parameter type, flattening union types.
     * Built-in types (including `null` from a nullable type) are omitted.
     *
     * @return list<class-string>
     */
    private static function classTypes(?ReflectionType $type): array
    {
        $parts = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        $classTypes = [];
        foreach ($parts as $part) {
            if ($part instanceof ReflectionNamedType && !$part->isBuiltin()) {
                /** @var class-string $name */
                $name = $part->getName();
                $classTypes[] = $name;
            }
        }

        return $classTypes;
    }

    public static function clearCache(): void
    {
        File::delete(self::getCacheFile());
    }

    /** @return Generator<int, ReflectionClass<object>> */
    private function getReflections(): Generator
    {
        foreach ($this->getRelevantClasses() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            yield new ReflectionClass($class);
        }
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
        foreach (Addon::getActivatedAddons() as $addon) {
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

    /** @return array<string, mixed> */
    private function loadCacheData(): array
    {
        if (null !== $this->cacheData) {
            return $this->cacheData;
        }

        // The cache is a plain PHP file (var_export + include) so it benefits from OpCache. It only ever holds
        // scalars, arrays and enums (attribute arguments are constant expressions), never arbitrary objects, so
        // including it cannot instantiate unexpected classes.
        $file = self::getCacheFile();
        if (!is_file($file)) {
            return $this->cacheData = [];
        }

        /** @var mixed $cache */
        $cache = include $file;

        if (!is_array($cache) || !isset($cache['addons_hash']) || $cache['addons_hash'] !== $this->getAddonHash()) {
            return $this->cacheData = [];
        }

        // In debug mode, check if PHP files were added, removed or modified since the cache was built
        if (Core::isDebugMode()) {
            if (!isset($cache['files_hash']) || $cache['files_hash'] !== $this->getPhpFilesHash()) {
                return $this->cacheData = [];
            }
        }

        /** @var array<string, mixed> $data */
        $data = $cache['data'] ?? [];

        return $this->cacheData = $data;
    }

    private function saveCacheData(string $cacheKey, mixed $data): void
    {
        $this->cacheData = $this->loadCacheData();
        $this->cacheData[$cacheKey] = $data;

        $file = self::getCacheFile();
        File::put($file, '<?php' . "\n\n" . 'return ' . var_export([
            'addons_hash' => $this->getAddonHash(),
            'files_hash' => $this->getPhpFilesHash(),
            'data' => $this->cacheData,
        ], true) . ';' . "\n");

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    private function getAddonHash(): string
    {
        $parts = [];
        foreach (Addon::getActivatedAddons() as $addon) {
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
        return Path::coreCache('class_discovery.php');
    }

    private static function findClassLoader(): ClassLoader
    {
        return array_first(ClassLoader::getRegisteredLoaders())
            ?? throw new RuntimeException('Composer ClassLoader not found.');
    }
}
