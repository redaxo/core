<?php

namespace Redaxo\Core\Content;

use InvalidArgumentException;
use Redaxo\Core\ClassDiscovery;

use function sprintf;

abstract class Template
{
    /** @var array<string, self>|null */
    private static ?array $instances = null;

    /** @var class-string<self>|null */
    private static ?string $defaultClass = null;

    public function __construct(
        /** Unique key, used as DB reference in rex_article.template. */
        public readonly string $key,
        public readonly string $name,
    ) {}

    /**
     * Sets the default template, used e.g. for new articles.
     *
     * Call this in your project's `boot()` method.
     *
     * @param class-string<self> $class
     */
    final public static function setDefault(string $class): void
    {
        if (null === self::get($class)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a registered template.', $class));
        }

        self::$defaultClass = $class;
    }

    /**
     * Returns the key of the default template.
     *
     * If no default has been set, the first available template is used.
     * Returns `null` only when no templates exist at all.
     */
    final public static function getDefaultKey(): ?string
    {
        if (null !== self::$defaultClass) {
            return self::get(self::$defaultClass)?->key;
        }

        return array_key_first(self::getAll());
    }

    /**
     * Get a template by key or FQCN.
     *
     * @param string|class-string<self> $keyOrClass
     */
    public static function get(string $keyOrClass): ?self
    {
        $all = self::getAll();

        /** @var ?self */
        return $all[$keyOrClass]
            // FQCN lookup
            ?? array_find($all, static fn (self $template) => $template instanceof $keyOrClass);
    }

    /**
     * Check if a template exists by key or FQCN.
     *
     * @param string|class-string<self> $keyOrClass
     */
    public static function exists(string $keyOrClass): bool
    {
        return null !== self::get($keyOrClass);
    }

    /** @return array<string, self> */
    public static function getAll(): array
    {
        if (null !== self::$instances) {
            return self::$instances;
        }

        $instances = [];
        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsTemplate::class, self::class) as $class => $attribute) {
            $instances[$attribute->key] = new $class($attribute->key, $attribute->name);
        }

        return self::$instances = $instances;
    }

    /**
     * Returns all templates available for the given category.
     *
     * @return array<string, self>
     */
    public static function getTemplatesForCategory(?int $categoryId): array
    {
        $category = $categoryId > 0 ? Category::get($categoryId) : null;

        return array_filter(
            self::getAll(),
            static fn (self $template) => $template->isAllowedInCategory($category),
        );
    }

    /**
     * Check if a module is allowed in a given content section of a template (resolves keys to objects).
     *
     * @param positive-int $contentSectionId
     * @internal
     */
    public static function checkModuleAllowed(string $templateKey, int $contentSectionId, string $moduleKey): bool
    {
        $template = self::get($templateKey);

        if (null === $template) {
            return true;
        }

        $module = Module::get($moduleKey);

        if (null === $module) {
            return false;
        }

        $section = $template->getContentSection($contentSectionId);

        if (null === $section) {
            return true;
        }

        return $template->isModuleAllowed($section, $module);
    }

    /** @return non-empty-list<ContentSection> */
    public function getContentSections(): array
    {
        return [new ContentSection(1, 'Content')];
    }

    /**
     * Whether this template is available in the given category.
     * `null` means root level (articles without a category).
     * The default implementation allows the template everywhere.
     *
     * Override this to restrict to specific categories.
     */
    public function isAllowedInCategory(?Category $category): bool
    {
        return true;
    }

    /**
     * Whether the given module is allowed in the given content section of this template.
     * The default implementation allows all modules everywhere.
     */
    public function isModuleAllowed(ContentSection $section, Module $module): bool
    {
        return true;
    }

    /** @param positive-int $id */
    final public function getContentSection(int $id): ?ContentSection
    {
        return array_find($this->getContentSections(), static fn (ContentSection $s) => $s->id === $id);
    }

    /** @param positive-int $id */
    final public function hasContentSection(int $id): bool
    {
        return null !== $this->getContentSection($id);
    }

    abstract public function render(ArticleContent $article): string;
}
