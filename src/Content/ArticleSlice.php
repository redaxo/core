<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;

use function is_array;
use function sprintf;

final readonly class ArticleSlice
{
    private const string ORDER_ASC = 'ASC';
    private const string ORDER_DESC = 'DESC';

    /**
     * @param array<int, string|null> $values
     * @param array<int, string|null> $media
     * @param array<int, string|null> $medialists
     * @param array<int, string|null> $links
     * @param array<int, string|null> $linklists
     */
    private function __construct(
        public int $id,
        public int $articleId,
        public int $clangId,
        public int $contentSectionId,
        public string $moduleKey,
        public int $priority,
        public int $status,
        public int $createdate,
        public int $updatedate,
        public string $createuser,
        public string $updateuser,
        public int $revision,
        private array $values,
        private array $media,
        private array $medialists,
        private array $links,
        private array $linklists,
    ) {}

    /** @internal  */
    public static function forNewSlice(
        int $articleId,
        int $clangId,
        int $ctype,
        string $moduleKey,
        int $priority,
        int $revision,
    ): self {
        return new self(
            0,
            $articleId,
            $clangId,
            $ctype,
            $moduleKey,
            $priority,
            1,
            $time = time(),
            $time,
            $user = Core::requireUser()->login,
            $user,
            $revision,
            array_fill(0, 20, null),
            array_fill(0, 10, null),
            array_fill(0, 10, null),
            array_fill(0, 10, null),
            array_fill(0, 10, null),
        );
    }

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
            (string) $sql->getValue($table . '.module'),
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
     * Returns a copy of this slice with the slice fields (`value*`, `media*`, `medialist*`, `link*`, `linklist*`)
     * overridden by the current `REX_INPUT_*` request values. Fields without a matching request entry are reset to `null`.
     *
     * Use this to show the user's pending form input in the edit form (e.g. when an in-form action like
     * "add row" re-submits before the slice has been persisted).
     *
     * @internal
     */
    public function withRequestValues(): self
    {
        $lists = self::readRequestValues();

        return new self(
            $this->id,
            $this->articleId,
            $this->clangId,
            $this->contentSectionId,
            $this->moduleKey,
            $this->priority,
            $this->status,
            $this->createdate,
            $this->updatedate,
            $this->createuser,
            $this->updateuser,
            $this->revision,
            $lists['value'],
            $lists['media'],
            $lists['medialist'],
            $lists['link'],
            $lists['linklist'],
        );
    }

    /**
     * Reads `REX_INPUT_VALUE`, `REX_INPUT_MEDIA`, `REX_INPUT_MEDIALIST`, `REX_INPUT_LINK`, `REX_INPUT_LINKLIST`
     * from the current request and returns them as fixed-size lists (20 entries for `value`, 10 for the rest).
     * Slots without a matching request entry are `null`; array values are JSON-encoded so they match the storage format.
     *
     * @return array{value: list<?string>, media: list<?string>, medialist: list<?string>, link: list<?string>, linklist: list<?string>}
     * @internal
     */
    public static function readRequestValues(): array
    {
        $result = [];
        foreach (['value' => 20, 'media' => 10, 'medialist' => 10, 'link' => 10, 'linklist' => 10] as $key => $max) {
            /** @var array<int, string|int|null> $requestValues */
            $requestValues = Request::request('REX_INPUT_' . strtoupper($key), 'array');
            $list = [];
            for ($i = 1; $i <= $max; ++$i) {
                $value = $requestValues[$i] ?? null;
                $list[] = is_array($value) ? json_encode($value) : (null === $value ? null : (string) $value);
            }
            $result[$key] = $list;
        }

        /** @var array{value: list<?string>, media: list<?string>, medialist: list<?string>, link: list<?string>, linklist: list<?string>} */
        return $result;
    }

    public static function getArticleSliceById(int $id, ?int $clang = null, int $revision = 0): ?self
    {
        $clang ??= Language::getCurrentId();

        return self::getSliceWhere(
            'id=? AND clang_id=? and revision=?',
            [$id, $clang, $revision],
        );
    }

    /**
     * Return the first slice for an article.
     * This can then be used to iterate over all the slices in the order as they appear using the getNextSlice() function.
     */
    public static function getFirstSliceForArticle(int $articleId, ?int $clang = null, int $revision = 0, bool $ignoreOfflines = false): ?self
    {
        $clang ??= Language::getCurrentId();

        foreach (range(1, 20) as $ctype) {
            $slice = self::getFirstSliceForCtype($ctype, $articleId, $clang, $revision, $ignoreOfflines);
            if (null !== $slice) {
                return $slice;
            }
        }

        return null;
    }

    /** Returns the first slice of the given ctype of an article. */
    public static function getFirstSliceForCtype(int $ctype, int $articleId, ?int $clang = null, int $revision = 0, bool $ignoreOfflines = false): ?self
    {
        $clang ??= Language::getCurrentId();

        return self::getSliceWhere(
            'article_id=? AND clang_id=? AND ctype_id=? AND priority=1 AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$articleId, $clang, $ctype, $revision],
        );
    }

    /**
     * Return all slices for an article that have a certain
     * clang or revision.
     *
     * @return list<self>
     */
    public static function getSlicesForArticle(int $articleId, ?int $clang = null, int $revision = 0, bool $ignoreOfflines = false): array
    {
        $clang ??= Language::getCurrentId();

        return self::getSlicesWhere(
            'article_id=? AND clang_id=? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$articleId, $clang, $revision],
        );
    }

    /**
     * Return all slices for an article that have a certain module type.
     *
     * @return list<self>
     */
    public static function getSlicesForArticleOfType(int $articleId, string $moduleKey, ?int $clang = null, int $revision = 0, bool $ignoreOfflines = false): array
    {
        $clang ??= Language::getCurrentId();

        return self::getSlicesWhere(
            'article_id=? AND clang_id=? AND module=? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$articleId, $clang, $moduleKey, $revision],
        );
    }

    public function getNextSlice(bool $ignoreOfflines = false): ?self
    {
        return self::getSliceWhere(
            'priority ' . ($ignoreOfflines ? '>=' : '=') . ' ? AND article_id=? AND clang_id = ? AND ctype_id = ? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$this->priority + 1, $this->articleId, $this->clangId, $this->contentSectionId, $this->revision],
        );
    }

    public function getPreviousSlice(bool $ignoreOfflines = false): ?self
    {
        return self::getSliceWhere(
            'priority ' . ($ignoreOfflines ? '<=' : '=') . ' ? AND article_id=? AND clang_id = ? AND ctype_id = ? AND revision=?' . ($ignoreOfflines ? ' AND status = 1' : ''),
            [$this->priority - 1, $this->articleId, $this->clangId, $this->contentSectionId, $this->revision],
            self::ORDER_DESC,
        );
    }

    /**
     * Gibt den Slice formatiert zurück.
     *
     * @see ArticleContent::getSlice()
     *
     * @throws ArticleNotFoundException
     */
    public function getSlice(): string
    {
        $art = new ArticleContent($this->articleId, $this->clangId);
        $art->sliceRevision = $this->revision;
        return $art->getSlice($this->id);
    }

    /**
     * @param literal-string $where
     * @param array<scalar|null> $params
     * @param self::ORDER_* $orderDirection
     *
     * @return self|null
     */
    private static function getSliceWhere(string $where, array $params = [], string $orderDirection = self::ORDER_ASC)
    {
        $slices = self::getSlicesWhere($where, $params, $orderDirection, 1);
        return $slices[0] ?? null;
    }

    /**
     * @param literal-string $where
     * @param array<scalar|null> $params
     * @param self::ORDER_* $orderDirection
     *
     * @return list<self>
     */
    private static function getSlicesWhere(string $where, array $params = [], string $orderDirection = self::ORDER_ASC, ?int $limit = null)
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

    public function getArticle(): Article
    {
        $article = Article::get($this->articleId);

        if (!$article) {
            throw new LogicException(sprintf('Article with id=%d not found.', $this->articleId));
        }

        return $article;
    }

    /** @param int<1, 20> $index */
    public function getValue(int $index): ?string
    {
        return $this->values[$index - 1];
    }

    /**
     * @param int<1, 20> $index
     * @return array<mixed>|null
     */
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

    /** @param int<1, 10> $index */
    public function getLink(int $index): ?int
    {
        $link = $this->links[$index - 1];

        return null === $link ? null : (int) $link;
    }

    /** @param int<1, 10> $index */
    public function getLinkUrl(int $index): ?string
    {
        $link = $this->getLink($index);

        return null === $link ? null : Url::article($link);
    }

    /**
     * @param int<1, 10> $index
     * @return string|null comma separated list
     */
    public function getLinkList(int $index): ?string
    {
        return $this->linklists[$index - 1];
    }

    /**
     * @param int<1, 10> $index
     * @return list<int>|null
     */
    public function getLinkListArray(int $index): ?array
    {
        $list = $this->linklists[$index - 1];

        if (null === $list) {
            return null;
        }

        return array_map('intval', explode(',', $list));
    }

    /** @param int<1, 10> $index */
    public function getMedia(int $index): ?string
    {
        return $this->media[$index - 1];
    }

    /** @param int<1, 10> $index */
    public function getMediaUrl(int $index): ?string
    {
        $media = $this->getMedia($index);

        return null === $media ? null : Url::media($media);
    }

    /**
     * @param int<1, 10> $index
     * @return string|null comma separated list
     */
    public function getMediaList(int $index): ?string
    {
        return $this->medialists[$index - 1];
    }

    /**
     * @param int<1, 10> $index
     * @return list<string>|null
     */
    public function getMediaListArray(int $index): ?array
    {
        $list = $this->medialists[$index - 1];

        if (null === $list) {
            return null;
        }

        return explode(',', $list);
    }

    public function isOnline(): bool
    {
        return 1 === $this->status;
    }

    /**
     * @internal
     * @param array{id: int, articleId: int, clangId: int, contentSectionId: int, moduleKey: string, priority: int, status: int, createdate: int, updatedate: int, createuser: string, updateuser: string, revision: int, values: array<int, string|null>, media: array<int, string|null>, medialists: array<int, string|null>, links: array<int, string|null>, linklists: array<int, string|null>} $data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            $data['id'],
            $data['articleId'],
            $data['clangId'],
            $data['contentSectionId'],
            $data['moduleKey'],
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
