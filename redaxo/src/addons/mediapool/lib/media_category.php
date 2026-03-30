<?php

/**
 * Object Oriented Framework: Bildet eine Kategorie im Medienpool ab.
 *
 * @package redaxo\mediapool
 */
class rex_media_category
{
    use rex_instance_list_pool_trait;
    use rex_instance_pool_trait;

    /** @var int */
    private $id;
    /** @var int */
    private $parentId;

    /** @var string */
    private $name = '';
    /** @var string */
    private $path = '';

    /** @var int */
    private $createdate;
    /** @var int */
    private $updatedate;

    /** @var string */
    private $createuser = '';
    /** @var string */
    private $updateuser = '';

    /**
     * @param int $id
     *
     * @return static|null
     */
    public static function get($id)
    {
        $id = (int) $id;

        if (0 >= $id) {
            return null;
        }

        return static::getInstance($id, static function ($id) {
            $catPath = rex_path::addonCache('mediapool', $id . '.mcat');
            $cache = rex_file::getCache($catPath);

            if (!$cache) {
                rex_media_cache::generateCategory($id);
                $cache = rex_file::getCache($catPath);
            }

            if ($cache) {
                $cat = new static();

                $cat->id = (int) $cache['id'];
                $cat->parentId = (int) $cache['parent_id'];

                $cat->name = (string) $cache['name'];
                $cat->path = (string) $cache['path'];

                $cat->createdate = (int) $cache['createdate'];
                $cat->updatedate = (int) $cache['updatedate'];

                $cat->createuser = (string) $cache['createuser'];
                $cat->updateuser = (string) $cache['updateuser'];

                return $cat;
            }

            return null;
        });
    }

    /** @return list<self> */
    public static function getRootCategories()
    {
        return self::getChildCategories(0);
    }

    /**
     * @param int $parentId
     *
     * @return list<self>
     */
    protected static function getChildCategories($parentId)
    {
        $parentId = (int) $parentId;
        // for $parentId=0 root categories will be returned, so abort here for $parentId<0 only
        if (0 > $parentId) {
            return [];
        }

        return self::getInstanceList([$parentId, 'children'], self::get(...), static function ($parentId) {
            $catlistPath = rex_path::addonCache('mediapool', $parentId . '.mclist');

            $list = rex_file::getCache($catlistPath, null);
            if (null === $list) {
                rex_media_cache::generateCategoryList($parentId);
                $list = rex_file::getCache($catlistPath);
            }

            return $list;
        });
    }

    /** @return int */
    public function getId()
    {
        return $this->id;
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /** @return string */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the path ids of the category as an array.
     *
     * @return list<int>
     */
    public function getPathAsArray()
    {
        $p = array_filter(explode('|', $this->path));

        return array_values(array_map('intval', $p));
    }

    /** @return string */
    public function getUpdateUser()
    {
        return $this->updateuser;
    }

    /** @return int */
    public function getUpdateDate()
    {
        return $this->updatedate;
    }

    /** @return string */
    public function getCreateUser()
    {
        return $this->createuser;
    }

    /** @return int */
    public function getCreateDate()
    {
        return $this->createdate;
    }

    /** @return int */
    public function getParentId()
    {
        return $this->parentId;
    }

    /** @return self|null */
    public function getParent()
    {
        return self::get($this->getParentId());
    }

    /**
     * Get an array of all parentCategories.
     * Returns an array of rex_media_category objects sorted by $priority.
     *
     * @return list<self>
     */
    public function getParentTree()
    {
        $tree = [];
        if ($this->path) {
            $explode = explode('|', $this->path);
            foreach ($explode as $var) {
                if ('' == $var) {
                    continue;
                }

                $category = self::get((int) $var);

                if (!$category) {
                    throw new LogicException(sprintf('Missing media category with id=%d.', $var));
                }

                $tree[] = $category;
            }
        }
        return $tree;
    }

    /**
     * Checks if $anObj is in the parent tree of the object.
     *
     * @param self $anObj
     *
     * @return bool
     */
    public function inParentTree($anObj)
    {
        $tree = $this->getParentTree();
        return in_array($anObj, $tree);
    }

    /** @return list<self> */
    public function getChildren()
    {
        return self::getChildCategories($this->getId());
    }

    /** @return list<rex_media> */
    public function getMedia()
    {
        return self::getInstanceList([$this->getId(), 'media'], rex_media::get(...), static function ($id) {
            $listPath = rex_path::addonCache('mediapool', $id . '.mlist');

            $list = rex_file::getCache($listPath, null);
            if (null === $list) {
                rex_media_cache::generateList($id);
                $list = rex_file::getCache($listPath);
            }

            return $list;
        });
    }

    /** @return bool */
    public function isParent(self $mediaCat)
    {
        return $this->getParentId() == $mediaCat->getId();
    }
}
