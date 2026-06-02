<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ClassDiscovery;

abstract class Module
{
    /** @var array<string, self>|null */
    private static ?array $instances = null;

    final public function __construct(
        /** Unique key, used as DB reference in rex_article_slice.module. */
        final public readonly string $key,
        final public readonly string $name,
    ) {}

    /**
     * Get a module by key or FQCN.
     *
     * @param string|class-string<self> $keyOrClass
     */
    final public static function get(string $keyOrClass): ?self
    {
        $all = self::getAll();

        /** @var ?self */
        return $all[$keyOrClass]
            // FQCN lookup
            ?? array_find($all, static fn (self $module) => $module instanceof $keyOrClass);
    }

    /**
     * Check if a module exists by key or FQCN.
     *
     * @param string|class-string<self> $keyOrClass
     */
    final public static function exists(string $keyOrClass): bool
    {
        return null !== self::get($keyOrClass);
    }

    /** @return array<string, self> */
    final public static function getAll(): array
    {
        if (null !== self::$instances) {
            return self::$instances;
        }

        $instances = [];
        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsModule::class, self::class) as $class => $attribute) {
            $instances[$attribute->key] = new $class($attribute->key, $attribute->name);
        }

        return self::$instances = $instances;
    }

    /**
     * Whether this module is allowed in the given category.
     * `null` means root level (articles without a category).
     * The default implementation allows the module everywhere.
     *
     * Override this to restrict to specific categories.
     */
    public function isAllowedInCategory(?Category $category): bool
    {
        return true;
    }

    /** Render the backend edit form. */
    abstract public function input(ArticleSlice $slice): string;

    /** Render the frontend output. */
    abstract public function output(ArticleSlice $slice): string;

    /** Called before slice data is saved to DB. */
    public function onPresave(ArticleSliceAction $action): void {}

    /** Called after slice data is saved to DB. */
    public function onPostsave(ArticleSliceAction $action): void {}
}
