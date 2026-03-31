<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;

use function is_array;
use function is_int;
use function sprintf;

/**
 * The ArticleSlice class is an object wrapper over the database table rex_article_slice.
 * Together with Article and Category it provides an object oriented
 * Framework for accessing vital parts of your website.
 * This framework can be used in Modules, Templates and PHP-Slices!
 */
class ArticleSlice
{
    protected const ORDER_ASC = 'ASC';
    protected const ORDER_DESC = 'DESC';

    /**
     * @param int $id
     * @param int $articleId
     * @param int $clang
     * @param int $ctype
     * @param int $moduleId
     * @param int $priority
     * @param int $status
     * @param int $createdate
     * @param int $updatedate
     * @param string $createuser
     * @param string $updateuser
     * @param int $revision
     * @param array<int, string|null> $values
     * @param array<int, string|null> $media
     * @param array<int, string|null> $medialists
     * @param array<int, string|null> $links
     * @param array<int, string|null> $linklists
     */
    protected function __construct(
        private $id,
        private $articleId,
        private $clang,
        private $ctype,
        private $moduleId,
        private $priority,
        private $status,
        private $createdate,
        private $updatedate,
        private $createuser,
        private $updateuser,
        private $revision,
        private $values,
        private $media,
        private $medialists,
        private $links,
        private $linklists,
    ) {}

    /** @internal  */
    public static function fromSql(Sql $sql): self
    {
        $table = Core::getTable('article_slice');

        $data = [];
        foreach (['value' => 20, 'media' => 10, 'medialist' => 10, 'link' => 10, 'linklist' => 10] as $list => $count) {
            for ($k = 1; $k <= $count; ++$k) {
                $value = $sql->getValue($table . '.' . $list . $k);
                $data[$list][] = null == $value ? null : (string) $value;
            }
        }

        return new self(
            (int) $sql->getValue($table . '.id'),
            (int) $sql->getValue($table . '.article_id'),
            (int) $sql->getValue($table . '.clang_id'),
            (int) $sql->getValue($table . '.ctype_id'),
            (int) $sql->getValue($table . '.module_id'),
            (int) $sql->getValue($table . '.priority'),
            (int) $sql->getValue($table . '.status'),
            (int) $sql->getDateTimeValue($table . '.createdate'),
            (int) $sql->getDateTimeValue($table . '.updatedate'),
            (string) $sql->getValue($table . '.createuser'),
            (string) $sql->getValue($table . '.updateuser'),
            (int) $sql->getValue($table . '.revision'),
            $data['value'],
            $data['media'],
            $data['medialist'],
            $data['link'],
            $data['linklist'],
        );
    }

    /**
     * Return an ArticleSlice by its id.
     *
     * @param int $anId
     * @param false|int $clang
     * @param int $revision
     *
     * @return self|null
     */
    public static function getArticleSliceById($anId, $clang = false, $revision = 0)
    {
        if (false === $clang) {
            $clang = Language::getCurrentId();
        }

        return self::getSliceWhere(
            'id=? AND clang_id=? and revision=?',
            [$anId, $clang, $revision],
        );
    }

    /**
     * Return the first slice for an article.
     * This can then be used to iterate over all the
     * slices in the order as they appear using the
     * getNextSlice() function.
     *
     * @param int $anArticleId
     * @param false|int $clang
     * @param int $revision
     * @param bool $ignoreOfflines
     *
     * @return self|null
     */
    public static function getFirstSliceForArticle($anArticleId, $clang = false, $revision = 0, $ignoreOfflines = false)
    {
        if (false === $clang) {
            $clang = Language::getCurrentId();
        }

        foreach (range(1, 20) as $ctype) {
            $slice = self::getFirstSliceForCtype($ctype, $anArticleId, $clang, $revision, $ignoreOfflines);
            if (null !== $slice) {
                return $slice;
            }
        }

        return null;
    }

    /**
     * Returns the first slice of the given ctype of an article.
     *
     * @param int $ctype
     * @param int $anArticleId
     * @param false|int $clang
     * @param int $revision
     * @param bool $ignoreOfflines
     *
     * @return self|null
     */
    public static function getFirstSliceForCtype($ctype, $anArticleId, $clang = false, $revision = 0, $ignoreOfflines = false)
    {
        if (false === $clang) {
            $clang = Language::getCurrentId();
        }

        return self::getSliceWhere(
            'article_id=? AND clang_id=? AND ctype_id=? AND priority=1 AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$anArticleId, $clang, $ctype, $revision],
        );
    }

    /**
     * Return all slices for an article that have a certain
     * clang or revision.
     *
     * @param int $anArticleId
     * @param false|int $clang
     * @param int $revision
     * @param bool $ignoreOfflines
     *
     * @return list<self>
     */
    public static function getSlicesForArticle($anArticleId, $clang = false, $revision = 0, $ignoreOfflines = false)
    {
        if (false === $clang) {
            $clang = Language::getCurrentId();
        }

        return self::getSlicesWhere(
            'article_id=? AND clang_id=? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$anArticleId, $clang, $revision],
        );
    }

    /**
     * Return all slices for an article that have a certain
     * module type.
     *
     * @param int $anArticleId
     * @param int $aModuletypeId
     * @param false|int $clang
     * @param int $revision
     * @param bool $ignoreOfflines
     *
     * @return list<self>
     */
    public static function getSlicesForArticleOfType($anArticleId, $aModuletypeId, $clang = false, $revision = 0, $ignoreOfflines = false)
    {
        if (false === $clang) {
            $clang = Language::getCurrentId();
        }

        return self::getSlicesWhere(
            'article_id=? AND clang_id=? AND module_id=? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$anArticleId, $clang, $aModuletypeId, $revision],
        );
    }

    /**
     * Return the next slice for this article.
     *
     * @param bool $ignoreOfflines
     *
     * @return self|null
     */
    public function getNextSlice($ignoreOfflines = false)
    {
        return self::getSliceWhere(
            'priority ' . ($ignoreOfflines ? '>=' : '=') . ' ? AND article_id=? AND clang_id = ? AND ctype_id = ? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$this->priority + 1, $this->articleId, $this->clang, $this->ctype, $this->revision],
        );
    }

    /**
     * @param bool $ignoreOfflines
     *
     * @return self|null
     */
    public function getPreviousSlice($ignoreOfflines = false)
    {
        return self::getSliceWhere(
            'priority ' . ($ignoreOfflines ? '<=' : '=') . ' ? AND article_id=? AND clang_id = ? AND ctype_id = ? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$this->priority - 1, $this->articleId, $this->clang, $this->ctype, $this->revision],
            self::ORDER_DESC,
        );
    }

    /**
     * Gibt den Slice formatiert zurück.
     *
     * @since 4.1 - 29.05.2008
     * @see ArticleContent::getSlice()
     *
     * @return string
     */
    public function getSlice()
    {
        $art = new ArticleContent();
        $art->setArticleId($this->getArticleId());
        $art->setClang($this->getClangId());
        $art->setSliceRevision($this->getRevision());
        return $art->getSlice($this->getId());
    }

    /**
     * @param literal-string $where
     * @param self::ORDER_* $orderDirection
     *
     * @return self|null
     */
    protected static function getSliceWhere($where, array $params = [], string $orderDirection = self::ORDER_ASC)
    {
        $slices = self::getSlicesWhere($where, $params, $orderDirection, 1);
        return $slices[0] ?? null;
    }

    /**
     * @param literal-string $where
     * @param self::ORDER_* $orderDirection
     *
     * @return list<self>
     */
    protected static function getSlicesWhere($where, array $params = [], string $orderDirection = 'ASC', ?int $limit = null)
    {
        $sql = Sql::factory();
        // $sql->setDebug();
        $query = '
            SELECT *
            FROM ' . Core::getTable('article_slice') . '
            WHERE ' . $where . '
            ORDER BY ctype_id ' . $orderDirection . ', priority ' . $orderDirection;

        if (null !== $limit) {
            $query .= ' LIMIT ' . $limit;
        }

        $sql->setQuery($query, $params);
        $rows = $sql->getRows();
        $slices = [];
        for ($i = 0; $i < $rows; ++$i) {
            $slices[] = self::fromSql($sql);

            $sql->next();
        }
        return $slices;
    }

    /** @return Article */
    public function getArticle()
    {
        $article = Article::get($this->getArticleId());

        if (!$article) {
            throw new LogicException(sprintf('Article with id=%d not found.', $this->getArticleId()));
        }

        return $article;
    }

    /** @return int */
    public function getArticleId()
    {
        return $this->articleId;
    }

    /** @return int */
    public function getClangId()
    {
        return $this->clang;
    }

    /** @return int */
    public function getCtype()
    {
        return $this->ctype;
    }

    /** @return int */
    public function getRevision()
    {
        return $this->revision;
    }

    /** @return int */
    public function getModuleId()
    {
        return $this->moduleId;
    }

    /** @return int */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @template T of int|string
     * @param T $index
     * @return (T is int ? string|null : int|string|null)
     */
    public function getValue($index)
    {
        if (is_int($index)) {
            return $this->values[(int) $index - 1];
        }

        return $this->$index ?? null;
    }

    public function getValueArray(int $index): ?array
    {
        $value = $this->values[$index - 1];

        if (null === $value) {
            return null;
        }

        /** @var mixed $value */
        $value = json_decode($value, true);

        return is_array($value) ? $value : null;
    }

    /**
     * @param int $index
     * @return int|null
     */
    public function getLink($index)
    {
        $link = $this->links[$index - 1];

        return null === $link ? null : (int) $link;
    }

    /**
     * @param int $index
     * @return string|null
     */
    public function getLinkUrl($index)
    {
        $link = $this->getLink($index);

        return null === $link ? null : Url::article($link);
    }

    /**
     * @param int $index
     * @return string|null liefert kommaseparierten String
     */
    public function getLinkList($index)
    {
        return $this->linklists[$index - 1];
    }

    /** @return list<int>|null */
    public function getLinkListArray(int $index): ?array
    {
        $list = $this->linklists[$index - 1];

        if (null === $list) {
            return null;
        }

        return array_map('intval', explode(',', $list));
    }

    /**
     * @param int $index
     * @return string|null
     */
    public function getMedia($index)
    {
        return $this->media[$index - 1];
    }

    /**
     * @param int $index
     * @return string|null
     */
    public function getMediaUrl($index)
    {
        $media = $this->getMedia($index);

        return null === $media ? null : Url::media($media);
    }

    /**
     * @param int $index
     * @return string|null liefert kommaseparierten String
     */
    public function getMediaList($index)
    {
        return $this->medialists[$index - 1];
    }

    /** @return list<string>|null */
    public function getMediaListArray(int $index): ?array
    {
        $list = $this->medialists[$index - 1];

        if (null === $list) {
            return null;
        }

        return explode(',', $list);
    }

    /** @return int */
    public function getPriority()
    {
        return $this->priority;
    }

    public function isOnline(): bool
    {
        return 1 == $this->status;
    }

    /**
     * @internal
     * @param array{id: int, articleId: int, clang: int, ctype: int, moduleId: int, priority: int, status: int, createdate: int, updatedate: int, createuser: string, updateuser: string, revision: int, values: array<int, string|null>, media: array<int, string|null>, medialists: array<int, string|null>, links: array<int, string|null>, linklists: array<int, string|null>} $data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            $data['id'],
            $data['articleId'],
            $data['clang'],
            $data['ctype'],
            $data['moduleId'],
            $data['priority'],
            $data['status'],
            $data['createdate'],
            $data['updatedate'],
            $data['createuser'],
            $data['updateuser'],
            $data['revision'],
            $data['values'],
            $data['media'],
            $data['medialists'],
            $data['links'],
            $data['linklists'],
        );
    }
}
