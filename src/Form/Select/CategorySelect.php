<?php

namespace Redaxo\Core\Form\Select;

use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;

use function is_array;

class CategorySelect extends Select
{
    /** @var int|list<int>|null */
    private int|array|null $rootId = null;

    private bool $loaded = false;

    public function __construct(
        private readonly bool $ignoreOfflines = false,
        private readonly ?int $clang = null,
        private readonly bool $checkPerms = true,
        private readonly bool $addHomepage = true,
    ) {
        parent::__construct();
    }

    /**
     * Kategorie-Id oder ein Array von Kategorie-Ids als Wurzelelemente der Select-Box.
     *
     * @param int|list<int>|null $rootId Kategorie-Id oder Array von Kategorie-Ids zur Identifikation der Wurzelelemente
     */
    public function setRootId(int|array|null $rootId): void
    {
        $this->rootId = $rootId;
    }

    protected function addCatOptions(): void
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

    protected function addCatOption(Category $cat, $group = null): void
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

    public function get(): string
    {
        if (!$this->loaded) {
            $this->addCatOptions();
            $this->loaded = true;
        }

        return parent::get();
    }

    protected function outGroup($parentId, $level = 0): string
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
