<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

use function in_array;
use function sprintf;

/**
 * Bildet eine Kategorie im Medienpool ab.
 */
final class MediaCategory
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    private function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $parentId,
        /** @var list<int> */
        public readonly array $path,
        public readonly int $createDate,
        public readonly int $updateDate,
        public readonly string $createUser,
        public readonly string $updateUser,
    ) {}

    public static function get(int $id): ?self
    {
        if (0 >= $id) {
            return null;
        }

        return self::getInstance($id, static function () use ($id): ?self {
            $catPath = Path::coreCache('mediapool/' . $id . '.mcat');
            $cache = File::getCache($catPath);

            if (!$cache) {
                MediaPoolCache::generateCategory($id);
                $cache = File::getCache($catPath);
            }

            if (!$cache) {
                return null;
            }

            /** @psalm-suppress MixedArgument */
            return new self(
                $cache['id'],
                $cache['name'],
                $cache['parent_id'],
                array_map('intval', explode('|', trim($cache['path'], '|'))),
                $cache['createdate'],
                $cache['updatedate'],
                $cache['createuser'],
                $cache['updateuser'],
            );
        });
    }

    /** @return list<self> */
    public static function getRootCategories(): array
    {
        return self::getChildCategories(0);
    }

    /** @return list<self> */
    private static function getChildCategories(int $parentId): array
    {
        // for $parentId=0 root categories will be returned, so abort here for $parentId<0 only
        if (0 > $parentId) {
            return [];
        }

        return self::getInstanceList([$parentId, 'children'], self::get(...), static function () use ($parentId) {
            $catlistPath = Path::coreCache('mediapool/' . $parentId . '.mclist');

            $list = File::getCache($catlistPath, null);
            if (null === $list) {
                MediaPoolCache::generateCategoryList($parentId);
                $list = File::getCache($catlistPath);
            }

            /** @var list<int> */
            return $list;
        });
    }

    public function getParent(): ?self
    {
        return $this->parentId ? self::get($this->parentId) : null;
    }

    /**
     * Get an array of all parent categories (root as the first element).
     *
     * @return list<self>
     */
    public function getParentTree(): array
    {
        $tree = [];
        foreach ($this->path as $id) {
            $category = self::get($id);

            if (!$category) {
                throw new LogicException(sprintf('Missing media category with id=%d.', $id));
            }

            $tree[] = $category;
        }

        return $tree;
    }

    /** Checks if $category is in the parent tree of this category. */
    public function inParentTree(self $category): bool
    {
        return in_array($category->id, $this->path, true);
    }

    /** @return list<self> */
    public function getChildren(): array
    {
        return self::getChildCategories($this->id);
    }

    /** @return list<Media> */
    public function getMedia(): array
    {
        $id = $this->id;

        return self::getInstanceList([$id, 'media'], Media::get(...), static function () use ($id) {
            $listPath = Path::coreCache('mediapool/' . $id . '.mlist');

            $list = File::getCache($listPath, null);
            if (null === $list) {
                MediaPoolCache::generateList($id);
                $list = File::getCache($listPath);
            }

            /** @var list<string> */
            return $list;
        });
    }

    /** Checks if $category is the parent of this category. */
    public function isParent(self $category): bool
    {
        return $this->parentId === $category->id;
    }
}
