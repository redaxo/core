<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Util\Str;

use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Basisklasse für die Strukturkomponenten.
 *
 * @psalm-import-type TUrlParams from Str
 */
abstract class StructureElement
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    /** Prefix used for meta-info fields of this element type (`art_` or `cat_`). */
    abstract protected string $metaInfoPrefix { get; }

    protected function __construct(
        public readonly int $id,
        public readonly int $languageId,
        public readonly string $name,
        public readonly int $priority,
        /** @var list<int> */
        public readonly array $path,
        public readonly int $status,
        public readonly int $createDate,
        public readonly int $updateDate,
        public readonly string $createUser,
        public readonly string $updateUser,
        /** @var array<string, string|int|null> */
        final protected readonly array $additionalData,
    ) {}

    /**
     * Return a StructureElement object based on an id.
     * The instance will be cached in an instance-pool and therefore re-used by a later call.
     *
     * @param int $id the article id
     * @param int|null $languageId the language id
     *
     * @return static|null A StructureElement instance typed to the late-static binding type of the caller
     */
    public static function get(int $id, ?int $languageId = null): ?static
    {
        if ($id <= 0) {
            return null;
        }

        if (!$languageId) {
            $languageId = Language::getCurrentId();
        }

        return static::getInstance([$id, $languageId], static function () use ($id, $languageId): ?static {
            $articlePath = Path::coreCache('structure/' . $id . '.' . $languageId . '.article');

            // load metadata from cache
            $metadata = File::getCache($articlePath);

            // generate cache if not exists
            if (!$metadata) {
                ArticleCache::generateMeta($id, $languageId);
                $metadata = File::getCache($articlePath);
            }

            // if cache does not exist after generation, the article id is invalid
            if (!$metadata) {
                return null;
            }

            /** @var array<string, string|int|null> $metadata */
            return static::fromCache($metadata);
        });
    }

    /**
     * Returns the element for the given id like {@see get()}, but throws when it does not exist.
     *
     * @throws RuntimeException if no element exists for the given id and language
     */
    public static function require(int $id, ?int $languageId = null): static
    {
        return static::get($id, $languageId)
            ?? throw new RuntimeException(sprintf('Required %s with id "%d" and language "%s" does not exist.', static::class, $id, $languageId ?? Language::getCurrentId()));
    }

    /**
     * Builds an instance from the cache row.
     *
     * @param array<string, string|int|null> $data
     */
    abstract protected static function fromCache(array $data): ?static;

    /** @return list<static> */
    final protected static function getChildElements(int $parentId, string $listType, bool $ignoreOfflines = false, ?int $languageId = null): array
    {
        // for $parentId=0 root elements will be returned, so abort here for $parentId<0 only
        if (0 > $parentId) {
            return [];
        }
        if (!$languageId) {
            $languageId = Language::getCurrentId();
        }

        $class = static::class;
        return static::getInstanceList(
            // list key
            [$parentId, $listType],
            // callback to get an instance for a given ID, status will be checked if $ignoreOfflines==true
            static function (int $id) use ($class, $ignoreOfflines, $languageId) {
                if ($instance = $class::get($id, $languageId)) {
                    return !$ignoreOfflines || $instance->isOnline() ? $instance : null;
                }
                return null;
            },
            // callback to create the list of IDs
            static function () use ($parentId, $listType) {
                $listFile = Path::coreCache('structure/' . $parentId . '.' . $listType);

                $list = File::getCache($listFile, null);
                if (null === $list) {
                    ArticleCache::generateLists($parentId);
                    $list = File::getCache($listFile);
                }

                /** @var list<int> */
                return $list;
            },
        );
    }

    /** @psalm-taint-source input */
    public function getValue(string $key): string|int|null
    {
        $key = strtolower($key);

        return match ($key) {
            'id' => $this->id,
            'language_id' => $this->languageId,
            'name' => $this->name,
            'priority' => $this->priority,
            'path' => $this->path ? '|' . implode('|', $this->path) . '|' : '|',
            'status' => $this->status,
            'createdate' => $this->createDate,
            'updatedate' => $this->updateDate,
            'createuser' => $this->createUser,
            'updateuser' => $this->updateUser,
            default => $this->additionalData[$key] ?? $this->additionalData[$this->metaInfoPrefix . $key] ?? null,
        };
    }

    /** Checks whether this element has a value for the given key. */
    public function hasValue(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, [
            'id', 'language_id', 'name', 'priority', 'path', 'status',
            'createdate', 'updatedate', 'createuser', 'updateuser',
        ], true)
            || array_key_exists($key, $this->additionalData)
            || array_key_exists($this->metaInfoPrefix . $key, $this->additionalData);
    }

    /**
     * Returns a url for linking to this article.
     *
     * @param TUrlParams $params
     */
    public function getUrl(array $params = []): string
    {
        return Url::article($this->id, $this->languageId, $params);
    }

    /**
     * Returns the next category upwards in the structure tree
     * (the containing category for articles, the parent category for categories).
     */
    abstract protected function getParent(): ?Category;

    /** Returns true if article is online. */
    public function isOnline(): bool
    {
        return 1 === $this->status;
    }

    /** Returns whether the element is permitted. */
    abstract public function isPermitted(): bool;

    /**
     * Get an array of all parent categories.
     *
     * @return list<Category>
     */
    public function getParentTree(): array
    {
        $return = [];

        foreach ($this->path as $id) {
            $cat = Category::get($id, $this->languageId);
            if (!$cat) {
                throw new LogicException('No category found with id=' . $id . ' and language=' . $this->languageId . '.');
            }
            $return[] = $cat;
        }

        return $return;
    }

    /** Checks if $anObj is in the parent tree of the object. */
    public function inParentTree(self $anObj): bool
    {
        return in_array($anObj, $this->getParentTree(), true);
    }

    /**
     * Returns the closest element (this element or any parent category) where the callback returns true.
     *
     * @param callable(self):bool $callback
     */
    public function getClosest(callable $callback): ?self
    {
        if ($callback($this)) {
            return $this;
        }

        return $this->getParent()?->getClosest($callback);
    }

    /** Returns the value from this element or from the closest parent category where the value is set. */
    public function getClosestValue(string $key): string|int|null
    {
        $value = $this->getValue($key);

        if (null !== $value && '' !== $value) {
            return $value;
        }

        return $this->getParent()?->getClosestValue($key);
    }

    /** Returns true if this element and all parent categories are online. */
    public function isOnlineIncludingParents(): bool
    {
        if (!$this->isOnline()) {
            return false;
        }

        $parent = $this->getParent();

        return !$parent || $parent->isOnlineIncludingParents();
    }
}
