<?php

namespace Redaxo\Core\Content;

use AllowDynamicProperties;
use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;

use function in_array;
use function is_string;
use function Redaxo\Core\View\escape;

/**
 * Object Oriented Framework: Basisklasse für die Strukturkomponenten.
 */
#[AllowDynamicProperties]
abstract class StructureElement
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    /** @var int */
    protected $id = 0;

    /** @var int */
    protected $parent_id = 0;

    /** @var int */
    protected $clang_id = 0;

    /** @var string */
    protected $name = '';

    /** @var string */
    protected $catname = '';

    /** @var int */
    protected $template_id = 0;

    /** @var string */
    protected $path = '';

    /** @var int */
    protected $priority = 0;

    /** @var int */
    protected $catpriority = 0;

    /** @var bool */
    protected $startarticle = false;

    /** @var int */
    protected $status = 0;

    /** @var int */
    protected $updatedate = 0;

    /** @var int */
    protected $createdate = 0;

    /** @var string */
    protected $updateuser = '';

    /** @var string */
    protected $createuser = '';

    /** @var list<string>|null */
    protected static $classVars;

    protected function __construct(array $params)
    {
        foreach (self::getClassVars() as $var) {
            if (!isset($params[$var])) {
                continue;
            }

            if (in_array($var, ['id', 'parent_id', 'clang_id', 'template_id', 'priority', 'catpriority', 'status', 'createdate', 'updatedate'], true)) {
                $this->$var = (int) $params[$var];
            } elseif ('startarticle' === $var) {
                $this->$var = (bool) $params[$var];
            } else {
                $this->$var = $params[$var];
            }
        }
    }

    /**
     * Returns Object Value.
     *
     * @param string $value
     * @return string|int|null
     * @psalm-taint-source input
     */
    public function getValue($value)
    {
        // damit alte rex_article felder wie teaser, online_from etc
        // noch funktionieren
        // gleicher BC code nochmals in article::getValue
        foreach (['', 'art_', 'cat_'] as $prefix) {
            $val = $prefix . $value;
            if (isset($this->$val)) {
                return $this->$val;
            }
        }
        return null;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected static function _hasValue($value, array $prefixes = [])
    {
        $values = self::getClassVars();

        if (in_array($value, $values)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (in_array($prefix . $value, $values)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns an Array containing article field names.
     *
     * @return list<string>
     */
    public static function getClassVars()
    {
        if (empty(self::$classVars)) {
            self::$classVars = [];

            $startId = Article::getSiteStartArticleId();
            $file = Path::coreCache('structure/' . $startId . '.1.article');
            if (!Core::isBackend() && is_file($file)) {
                // da getClassVars() eine statische Methode ist, können wir hier nicht mit $this->getId() arbeiten!
                $genVars = File::getCache($file, []);
                unset($genVars['last_update_stamp']);
                foreach ($genVars as $name => $value) {
                    self::$classVars[] = (string) $name;
                }
            } else {
                // Im Backend die Spalten aus der DB auslesen / via EP holen
                $sql = Sql::factory();
                $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article LIMIT 0');
                foreach ($sql->getFieldnames() as $field) {
                    self::$classVars[] = $field;
                }
            }
        }

        return self::$classVars;
    }

    /** @return void */
    public static function resetClassVars()
    {
        self::$classVars = null;
    }

    /**
     * Return a StructureElement object based on an id.
     * The instance will be cached in an instance-pool and therefore re-used by a later call.
     *
     * @param int $id the article id
     * @param int|null $clang the clang id
     *
     * @return static|null A StructureElement instance typed to the late-static binding type of the caller
     */
    public static function get(int $id, ?int $clang = null): ?static
    {
        if ($id <= 0) {
            return null;
        }

        if (!$clang) {
            $clang = Language::getCurrentId();
        }

        return static::getInstance([$id, $clang], static function () use ($id, $clang) {
            $articlePath = Path::coreCache('structure/' . $id . '.' . $clang . '.article');

            // load metadata from cache
            $metadata = File::getCache($articlePath);

            // generate cache if not exists
            if (!$metadata) {
                ArticleCache::generateMeta($id, $clang);
                $metadata = File::getCache($articlePath);
            }

            // if cache does not exist after generation, the article id is invalid
            if (!$metadata) {
                return null;
            }

            // don't allow to retrieve non-categories (startarticle=0) as Category
            if (!$metadata['startarticle'] && (Category::class === static::class || is_subclass_of(static::class, Category::class))) {
                return null;
            }

            return new static($metadata);
        });
    }

    /** @return list<static> */
    protected static function getChildElements(int $parentId, string $listType, bool $ignoreOfflines = false, ?int $clang = null): array
    {
        // for $parentId=0 root elements will be returned, so abort here for $parentId<0 only
        if (0 > $parentId) {
            return [];
        }
        if (!$clang) {
            $clang = Language::getCurrentId();
        }

        $class = static::class;
        return static::getInstanceList(
            // list key
            [$parentId, $listType],
            // callback to get an instance for a given ID, status will be checked if $ignoreOfflines==true
            static function (int $id) use ($class, $ignoreOfflines, $clang) {
                if ($instance = $class::get($id, $clang)) {
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

    /**
     * Returns the clang of the category.
     *
     * @return int
     */
    public function getClangId()
    {
        return $this->clang_id;
    }

    /**
     * Returns a url for linking to this article.
     *
     * @return string
     */
    public function getUrl(array $params = [])
    {
        return Url::article($this->getId(), $this->getClangId(), $params);
    }

    /**
     * Returns the id of the article.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the parent_id of the article.
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * Returns the path of the category/article.
     *
     * @return string
     */
    abstract public function getPath();

    /**
     * Returns the path ids of the category/article as an array.
     *
     * @return list<int>
     */
    public function getPathAsArray()
    {
        $path = explode('|', $this->getPath());
        return array_values(array_map('intval', array_filter($path)));
    }

    /**
     * Returns the parent category.
     *
     * @return static|null
     */
    abstract public function getParent();

    /**
     * Returns the name of the article.
     *
     * @return string
     * @psalm-taint-source input
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the article priority.
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Returns the last update user.
     *
     * @return string
     */
    public function getUpdateUser()
    {
        return $this->updateuser;
    }

    /**
     * Returns the last update date.
     *
     * @return int
     */
    public function getUpdateDate()
    {
        return $this->updatedate;
    }

    /**
     * Returns the creator.
     *
     * @return string
     */
    public function getCreateUser()
    {
        return $this->createuser;
    }

    /**
     * Returns the creation date.
     *
     * @return int
     */
    public function getCreateDate()
    {
        return $this->createdate;
    }

    /**
     * Returns true if article is online.
     *
     * @return bool
     */
    public function isOnline()
    {
        return 1 == $this->status;
    }

    /**
     * Returns the template id.
     *
     * @return int
     */
    public function getTemplateId()
    {
        return $this->template_id;
    }

    /**
     * Returns true if article has a template.
     *
     * @return bool
     */
    public function hasTemplate()
    {
        return $this->template_id > 0;
    }

    /**
     * Returns whether the element is permitted.
     *
     * @return bool
     */
    abstract public function isPermitted();

    /**
     * Returns a link to this article.
     *
     * @param array $params Parameter für den Link
     * @param array $attributes Attribute die dem Link hinzugefügt werden sollen. Default: array
     * @param string $sorroundTag HTML-Tag-Name mit dem der Link umgeben werden soll, z.b. 'li', 'div'. Default: null
     * @param array $sorroundAttributes Attribute die Umgebenden-Element hinzugefügt werden sollen. Default: array
     *
     * @return string
     */
    public function toLink(array $params = [], array $attributes = [], $sorroundTag = null, array $sorroundAttributes = [])
    {
        $name = $this->getName();
        $link = '<a href="' . $this->getUrl($params) . '"' . $this->_toAttributeString($attributes) . ' title="' . escape($name) . '">' . escape($name) . '</a>';

        if (null !== $sorroundTag && is_string($sorroundTag)) {
            $link = '<' . $sorroundTag . $this->_toAttributeString($sorroundAttributes) . '>' . $link . '</' . $sorroundTag . '>';
        }

        return $link;
    }

    /** @return string */
    protected function _toAttributeString(array $attributes)
    {
        $attr = '';

        foreach ($attributes as $name => $value) {
            $attr .= ' ' . $name . '="' . $value . '"';
        }

        return $attr;
    }

    /**
     * Get an array of all parentCategories.
     * Returns an array of StructureElement objects.
     *
     * @return list<Category>
     */
    public function getParentTree()
    {
        $return = [];

        if ($this->path) {
            if ($this->isStartArticle()) {
                $explode = explode('|', $this->path . $this->id . '|');
            } else {
                $explode = explode('|', $this->path);
            }

            foreach ($explode as $var) {
                if ('' != $var) {
                    $cat = Category::get((int) $var, $this->clang_id);
                    if (!$cat) {
                        throw new LogicException('No category found with id=' . $var . ' and clang=' . $this->clang_id . '.');
                    }
                    $return[] = $cat;
                }
            }
        }

        return $return;
    }

    /**
     * Checks if $anObj is in the parent tree of the object.
     *
     * @return bool
     */
    public function inParentTree(self $anObj)
    {
        $tree = $this->getParentTree();
        return in_array($anObj, $tree);
    }

    /**
     * Returns the closest element from parent tree (including itself) where the callback returns true.
     *
     * @param callable(self):bool $callback
     */
    public function getClosest(callable $callback): ?self
    {
        if ($callback($this)) {
            return $this;
        }

        $parent = $this->getParent();

        return $parent ? $parent->getClosest($callback) : null;
    }

    /**
     * Returns the value from this element or from the closest parent where the value is set.
     *
     * @return string|int|null
     */
    public function getClosestValue(string $key)
    {
        $value = $this->getValue($key);

        if (null !== $value && '' !== $value) {
            return $value;
        }

        $parent = $this->getParent();

        return $parent ? $parent->getClosestValue($key) : null;
    }

    /** Returns true if this element and all parents are online. */
    public function isOnlineIncludingParents(): bool
    {
        if (!$this->isOnline()) {
            return false;
        }

        $parent = $this->getParent();

        return !$parent || $parent->isOnlineIncludingParents();
    }

    /**
     * Returns true if this Article is the Startpage for the category.
     *
     * @return bool
     */
    public function isStartArticle()
    {
        return $this->startarticle;
    }

    /**
     * Returns true if this Article is the Startpage for the entire site.
     *
     * @return bool
     */
    public function isSiteStartArticle()
    {
        return $this->id == Article::getSiteStartArticleId();
    }

    /**
     * Returns  true if this Article is the not found article.
     *
     * @return bool
     */
    public function isNotFoundArticle()
    {
        return $this->id == Article::getNotfoundArticleId();
    }
}
