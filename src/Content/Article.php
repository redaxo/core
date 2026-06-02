<?php

namespace Redaxo\Core\Content;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function in_array;

/**
 * Bildet einen Artikel der Struktur ab.
 */
final class Article extends StructureElement
{
    protected string $metaInfoPrefix = 'art_';

    public readonly ?int $categoryId;
    public readonly ?string $templateKey;
    public readonly bool $startArticle;

    /** @param array<string, string|int|null> $data */
    private function __construct(array $data)
    {
        // strip irrelevant + Category-only fields up front; the rest gets explicitly
        // pulled into typed properties below, what remains lands in additionalData.
        unset($data['pid'], $data['catname'], $data['catpriority']);
        foreach (array_keys($data) as $key) {
            if (str_starts_with((string) $key, 'cat_')) {
                unset($data[$key]);
            }
        }

        $getAndUnset = static function (string $key) use (&$data): string|int|null {
            $value = $data[$key] ?? null;
            unset($data[$key]);
            return $value;
        };

        $id = (int) $getAndUnset('id');
        $parentId = (int) $getAndUnset('parent_id');
        $startArticle = (bool) $getAndUnset('startarticle');
        $path = array_map('intval', array_filter(explode('|', (string) $getAndUnset('path'))));
        // start-articles share the cache row with their category; their DB path
        // only includes ancestor categories, so add their own id to keep the
        // semantic "all category ids on the way to this element"
        if ($startArticle) {
            $path[] = $id;
        }

        parent::__construct(
            id: $id,
            clangId: (int) $getAndUnset('clang_id'),
            name: (string) $getAndUnset('name'),
            priority: (int) $getAndUnset('priority'),
            path: array_values($path),
            status: (int) $getAndUnset('status'),
            createDate: (int) $getAndUnset('createdate'),
            updateDate: (int) $getAndUnset('updatedate'),
            createUser: (string) $getAndUnset('createuser'),
            updateUser: (string) $getAndUnset('updateuser'),
            additionalData: $data,
        );

        $this->categoryId = $startArticle ? $id : ($parentId > 0 ? $parentId : null);
        $this->templateKey = null === ($t = $getAndUnset('template')) ? null : (string) $t;
        $this->startArticle = $startArticle;
    }

    /** @param array<string, string|int|null> $data */
    #[Override]
    protected static function fromCache(array $data): static
    {
        return new self($data);
    }

    /** Return the current article id. */
    public static function getCurrentId(): int
    {
        return Core::getProperty('article_id', 1);
    }

    /** Return the current article. */
    public static function getCurrent(?int $clang = null): ?self
    {
        return self::get(self::getCurrentId(), $clang);
    }

    /** Return the site wide start article id. */
    public static function getSiteStartArticleId(): int
    {
        return Core::getProperty('start_article_id', 1);
    }

    /** Return the site wide start article. */
    public static function getSiteStartArticle(?int $clang = null): ?self
    {
        return self::get(self::getSiteStartArticleId(), $clang);
    }

    /** Return the site wide notfound article id. */
    public static function getNotfoundArticleId(): int
    {
        return Core::getProperty('notfound_article_id', 1);
    }

    /** Return the site wide notfound article. */
    public static function getNotfoundArticle(?int $clang = null): ?self
    {
        return self::get(self::getNotfoundArticleId(), $clang);
    }

    /**
     * Return a list of top-level articles.
     *
     * @return list<self>
     */
    public static function getRootArticles(bool $ignoreOfflines = false, ?int $clang = null): array
    {
        return self::getChildElements(0, 'alist', $ignoreOfflines, $clang);
    }

    /** Returns the category this article belongs to (for start-articles the category itself). */
    public function getCategory(): ?Category
    {
        return null === $this->categoryId ? null : Category::get($this->categoryId, $this->clangId);
    }

    #[Override]
    protected function getParent(): ?Category
    {
        $category = $this->getCategory();
        return $this->startArticle ? $category?->getParent() : $category;
    }

    /** Returns true if this article is the start-article for its category. */
    public function isStartArticle(): bool
    {
        return $this->startArticle;
    }

    /** Returns true if this article is the site-wide start article. */
    public function isSiteStartArticle(): bool
    {
        return $this->id === self::getSiteStartArticleId();
    }

    /** Returns true if this article is the site-wide not-found article. */
    public function isNotFoundArticle(): bool
    {
        return $this->id === self::getNotfoundArticleId();
    }

    #[Override]
    public function getValue(string $key): string|int|null
    {
        $key = strtolower($key);

        return match ($key) {
            'category_id' => $this->categoryId,
            'template' => $this->templateKey,
            'startarticle' => (int) $this->startArticle,
            default => parent::getValue($key),
        };
    }

    #[Override]
    public function hasValue(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, ['template', 'startarticle', 'category_id'], true)
            || parent::hasValue($key);
    }

    #[Override]
    public function isPermitted(): bool
    {
        return (bool) Extension::dispatch(new ExtensionPoint('ART_IS_PERMITTED', true, ['element' => $this]));
    }
}
