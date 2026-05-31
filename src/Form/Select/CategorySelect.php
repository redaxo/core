<?php

namespace Redaxo\Core\Form\Select;

use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;

use function is_array;

class CategorySelect extends Select
{
    /** @var bool */
    private $ignoreOfflines;
    /** @var int|null */
    private $clang;
    /** @var bool */
    private $checkPerms;
    /** @var bool */
    private $addHomepage;

    /** @var int|list<int>|null */
    private $rootId;

    /** @var bool */
    private $loaded = false;

    public function __construct($ignoreOfflines = false, $clang = false, $checkPerms = true, $addHomepage = true)
    {
        $this->ignoreOfflines = $ignoreOfflines;
        $this->clang = false === $clang ? null : $clang;
        $this->checkPerms = $checkPerms;
        $this->addHomepage = $addHomepage;

        parent::__construct();
    }

    /**
     * Kategorie-Id oder ein Array von Kategorie-Ids als Wurzelelemente der Select-Box.
     *
     * @param int|list<int>|null $rootId Kategorie-Id oder Array von Kategorie-Ids zur Identifikation der Wurzelelemente
     * @return void
     */
    public function setRootId($rootId)
    {
        $this->rootId = $rootId;
    }

    /** @return void */
    protected function addCatOptions()
    {
        if ($this->addHomepage) {
            $this->addOption('Homepage', 0);
        }

        if (null !== $this->rootId) {
            if (is_array($this->rootId)) {
                foreach ($this->rootId as $rootId) {
                    if ($rootCat = Category::get($rootId, $this->clang)) {
                        $this->addCatOption($rootCat, 0);
                    }
                }
            } else {
                if ($rootCat = Category::get($this->rootId, $this->clang)) {
                    $this->addCatOption($rootCat, 0);
                }
            }
        } else {
            $perm = Core::requireUser()->getComplexPerm('structure');

            if (!$this->checkPerms || $perm->hasCategoryPerm(0)) {
                if ($rootCats = Category::getRootCategories($this->ignoreOfflines, $this->clang)) {
                    foreach ($rootCats as $rootCat) {
                        $this->addCatOption($rootCat);
                    }
                }
            } elseif ($perm->hasMountpoints()) {
                $mountpoints = $perm->getMountpointCategories();
                foreach ($mountpoints as $cat) {
                    if (!$this->ignoreOfflines || $cat->isOnline()) {
                        $this->addCatOption($cat, 0);
                    }
                }
            }
        }
    }

    /** @return void */
    protected function addCatOption(Category $cat, $group = null)
    {
        if (!$this->checkPerms || Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($cat->id)
        ) {
            $cid = $cat->id;
            $cname = $cat->name . ' [' . $cid . ']';

            if (null === $group) {
                $group = $cat->parentId ?? 0;
            }

            $this->addOption($cname, $cid, $cid, $group);
            foreach ($cat->getChildren($this->ignoreOfflines) as $child) {
                $this->addCatOption($child);
            }
        }
    }

    public function get()
    {
        if (!$this->loaded) {
            $this->addCatOptions();
            $this->loaded = true;
        }

        return parent::get();
    }

    protected function outGroup($parentId, $level = 0)
    {
        if ($level > 100) {
            // nur mal so zu sicherheit .. man weiss nie ;)
            throw new LogicException('select->outGroup overflow.');
        }

        $ausgabe = '';
        $group = $this->getGroup($parentId);
        if (!is_array($group)) {
            return '';
        }
        foreach ($group as $option) {
            $name = $option[0];
            $value = $option[1];
            $id = $option[2];
            if (0 == $id || !$this->checkPerms || Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($option[2])) {
                $ausgabe .= $this->outOption($name, $value, $level);
            } elseif ($this->checkPerms && Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($option[2])) {
                --$level;
            }

            $subgroup = $this->getGroup($id, true);
            if (false !== $subgroup) {
                $ausgabe .= $this->outGroup($id, $level + 1);
            }
        }
        return $ausgabe;
    }
}
