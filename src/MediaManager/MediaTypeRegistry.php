<?php

namespace Redaxo\Core\MediaManager;

use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\MediaManager\Attribute\AsMediaType;
use ReflectionClass;

use function array_map;
use function assert;

/**
 * Registry of all {@see MediaType} classes registered via {@see AsMediaType}.
 *
 * Types are discovered through {@see ClassDiscovery} (same mechanism as `#[AsCommand]`), so any
 * class in core or an addon carrying the attribute is picked up automatically — no manual
 * registration. As {@see AsMediaType} is repeatable, one class can register several named types,
 * each with its own constructor arguments.
 *
 * @internal
 */
final class MediaTypeRegistry
{
    /** @var array<string, array{class: class-string<MediaType>, arguments: array<string, mixed>}>|null */
    private static ?array $map = null;

    /** @var array<string, string> */
    private static array $sourceHashes = [];

    public static function has(string $name): bool
    {
        return isset(self::map()[$name]);
    }

    public static function get(string $name): ?MediaType
    {
        $entry = self::map()[$name] ?? null;

        return null === $entry ? null : new $entry['class'](...$entry['arguments']);
    }

    /** @return array<string, class-string<MediaType>> Map of type name to class */
    public static function all(): array
    {
        return array_map(static fn (array $entry): string => $entry['class'], self::map());
    }

    /**
     * Hash of the type class' source file, used as part of the cache key so that editing a type
     * automatically invalidates its cached files. Names backed by the same class share the hash.
     */
    public static function sourceHash(string $name): string
    {
        if (isset(self::$sourceHashes[$name])) {
            return self::$sourceHashes[$name];
        }

        $entry = self::map()[$name] ?? null;

        if (null === $entry) {
            return self::$sourceHashes[$name] = '';
        }

        $file = new ReflectionClass($entry['class'])->getFileName();
        assert(false !== $file);

        $hash = hash_file('xxh128', $file);

        return self::$sourceHashes[$name] = false === $hash ? '' : $hash;
    }

    /** @internal Reset the discovery cache (mainly for tests). */
    public static function reset(): void
    {
        self::$map = null;
        self::$sourceHashes = [];
    }

    /** @return array<string, array{class: class-string<MediaType>, arguments: array<string, mixed>}> */
    private static function map(): array
    {
        if (null !== self::$map) {
            return self::$map;
        }

        $map = [];

        // discoverByAttribute yields each class once; re-reflect to read all (repeatable) attributes
        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsMediaType::class, MediaType::class) as $class => $_attribute) {
            foreach (new ReflectionClass($class)->getAttributes(AsMediaType::class) as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                $map[$attribute->name] = ['class' => $class, 'arguments' => $attribute->arguments];
            }
        }

        return self::$map = $map;
    }
}
