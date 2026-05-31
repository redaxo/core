<?php

namespace Redaxo\Core\Content;

use Override;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function in_array;

/**
 * Bildet eine Kategorie der Struktur ab.
 */
final class Category extends StructureElement
{
    protected string $metaInfoPrefix = 'cat_';

    public readonly ?int $parentId;

    /** @param array<string, string|int|null> $data */
    private function __construct(array $data)
    {
        // strip irrelevant + Article-only fields up front
        unset(
            $data['pid'],
            $data['name'], $data['priority'], $data['template'], $data['startarticle'],
        );
        foreach (array_keys($data) as $key) {
            if (str_starts_with((string) $key, 'art_')) {
                unset($data[$key]);
            }
        }

        $getAndUnset = static function (string $key) use (&$data): string|int|null {
            $value = $data[$key] ?? null;
            unset($data[$key]);
            return $value;
        };

        $parentId = (int) $getAndUnset('parent_id');

        parent::__construct(
            id: (int) $getAndUnset('id'),
            clangId: (int) $getAndUnset('clang_id'),
            name: (string) $getAndUnset('catname'),
            priority: (int) $getAndUnset('catpriority'),
            path: array_values(array_map('intval', array_filter(explode('|', (string) $getAndUnset('path'))))),
            status: (int) $getAndUnset('status'),
            createDate: (int) $getAndUnset('createdate'),
            updateDate: (int) $getAndUnset('updatedate'),
            createUser: (string) $getAndUnset('createuser'),
            updateUser: (string) $getAndUnset('updateuser'),
            additionalData: $data,
        );

        $this->parentId = $parentId > 0 ? $parentId : null;
    }

    /** @param array<string, string|int|null> $data */
    #[Override]
    protected static function fromCache(array $data): ?static
    {
        // categories only exist for start-articles (which share their cache row)
        if (!$data['startarticle']) {
            return null;
        }

        return new self($data);
    }

    /** Return the current category. */
    public static function getCurrent(?int $clang = null): ?self
    {
        $article = Article::getCurrent($clang);

        return $article?->getCategory();
    }

    /**
     * Return a list of top level categories, ie.
     * categories that have no parent.
     * Returns an array of Category objects sorted by $priority.
     *
     * If $ignore_offlines is set to TRUE,
     * all categories with status 0 will be
     * excempt from this list!
     *
     * @return list<self>
     */
    public static function getRootCategories(bool $ignoreOfflines = false, ?int $clang = null): array
    {
        return self::getChildElements(0, 'clist', $ignoreOfflines, $clang);
    }

    /**
     * Return a list of all subcategories.
     * Returns an array of Category objects sorted by $priority.
     *
     * If $ignore_offlines is set to TRUE,
     * all categories with status 0 will be
     * excempt from this list!
     *
     * @return list<self>
     */
    public function getChildren(bool $ignoreOfflines = false): array
    {
        return self::getChildElements($this->id, 'clist', $ignoreOfflines, $this->clangId);
    }

    /** Returns the parent category. */
    #[Override]
    public function getParent(): ?self
    {
        return null === $this->parentId ? null : self::get($this->parentId, $this->clangId);
    }

    /** Returns TRUE if this category is the direct parent of the other category. */
    public function isParent(self $otherCat): bool
    {
        return $this->id === $otherCat->parentId && $this->clangId === $otherCat->clangId;
    }

    /**
     * Return a list of articles in this category
     * Returns an array of {@see Article} objects sorted by $priority.
     *
     * If $ignore_offlines is set to TRUE,
     * all articles with status 0 will be
     * excempt from this list!
     *
     * @return list<Article>
     */
    public function getArticles(bool $ignoreOfflines = false): array
    {
        return Article::getChildElements($this->id, 'alist', $ignoreOfflines, $this->clangId);
    }

    /** Return the start article for this category. */
    public function getStartArticle(): Article
    {
        return Article::require($this->id, $this->clangId);
    }

    #[Override]
    public function getValue(string $key): string|int|null
    {
        return match (strtolower($key)) {
            'catname' => $this->name,
            'catpriority' => $this->priority,
            'parent_id' => $this->parentId,
            default => parent::getValue($key),
        };
    }

    #[Override]
    public function hasValue(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, ['catname', 'catpriority', 'parent_id'], true)
            || parent::hasValue($key);
    }

    #[Override]
    public function isPermitted(): bool
    {
        return (bool) Extension::registerPoint(new ExtensionPoint('CAT_IS_PERMITTED', true, ['element' => $this]));
    }
}
