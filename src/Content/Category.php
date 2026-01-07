<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function assert;

/**
 * Object Oriented Framework: Bildet eine Kategorie der Struktur ab.
 */
class Category extends StructureElement
{
    /**
     * Return the current category.
     *
     * @param int $clang
     *
     * @return self|null
     */
    public static function getCurrent($clang = null)
    {
        $article = Article::getCurrent($clang);

        return $article ? $article->getCategory() : null;
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
     * @param bool $ignoreOfflines
     * @param int $clang
     *
     * @return list<self>
     */
    public static function getRootCategories($ignoreOfflines = false, $clang = null)
    {
        return self::getChildElements(0, 'clist', $ignoreOfflines, $clang);
    }

    public function getPriority()
    {
        return $this->catpriority;
    }

    /**
     * Return a list of all subcategories.
     * Returns an array of Category objects sorted by $priority.
     *
     * If $ignore_offlines is set to TRUE,
     * all categories with status 0 will be
     * excempt from this list!
     *
     * @param bool $ignoreOfflines
     *
     * @return list<self>
     */
    public function getChildren($ignoreOfflines = false)
    {
        return self::getChildElements($this->id, 'clist', $ignoreOfflines, $this->clang_id);
    }

    /**
     * Returns the parent category.
     *
     * @return static|null
     */
    public function getParent()
    {
        return self::get($this->parent_id, $this->clang_id);
    }

    /**
     * Returns TRUE if this category is the direct
     * parent of the other category.
     *
     * @return bool
     */
    public function isParent(self $otherCat)
    {
        return $this->getId() == $otherCat->getParentId()
             && $this->getClangId() == $otherCat->getClangId();
    }

    /**
     * Return a list of articles in this category
     * Returns an array of {@see Article} objects sorted by $priority.
     *
     * If $ignore_offlines is set to TRUE,
     * all articles with status 0 will be
     * excempt from this list!
     *
     * @param bool $ignoreOfflines
     *
     * @return list<Article>
     */
    public function getArticles($ignoreOfflines = false)
    {
        return Article::getChildElements($this->id, 'alist', $ignoreOfflines, $this->clang_id);
    }

    /**
     * Return the start article for this category.
     *
     * @return Article
     */
    public function getStartArticle()
    {
        $article = Article::get($this->id, $this->clang_id);
        assert($article instanceof Article);
        return $article;
    }

    /**
     * Returns the name of the category.
     *
     * @return string
     */
    public function getName()
    {
        return $this->catname;
    }

    /**
     * Returns the path of the category.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function hasValue($value)
    {
        return parent::_hasValue($value, ['cat_']);
    }

    public function isPermitted()
    {
        return (bool) Extension::registerPoint(new ExtensionPoint('CAT_IS_PERMITTED', true, ['element' => $this]));
    }
}
