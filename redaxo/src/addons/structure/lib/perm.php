<?php

/**
 * @package redaxo\structure
 */
class rex_structure_perm extends rex_complex_perm
{
    /**
     * @param int $categoryId
     *
     * @return bool
     */
    public function hasCategoryPerm($categoryId)
    {
        if ($this->hasAll() || in_array($categoryId, $this->perms)) {
            return true;
        }
        if ($c = rex_category::get($categoryId)) {
            foreach ($c->getPathAsArray() as $k) {
                if (in_array($k, $this->perms)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @return bool */
    public function hasStructurePerm()
    {
        return $this->hasAll() || count($this->perms) > 0;
    }

    /** @return array */
    public function getMountpoints()
    {
        return $this->hasAll() ? [] : $this->perms;
    }

    /** @return bool */
    public function hasMountpoints()
    {
        return !$this->hasAll() && count($this->perms) > 0;
    }

    /** @return list<rex_category> */
    public function getMountpointCategories(): array
    {
        if ($this->hasAll()) {
            return [];
        }

        $categories = [];
        $parents = [];
        foreach ($this->perms as $id) {
            $category = rex_category::get($id);
            if (!$category) {
                continue;
            }

            $categories[] = $category;
            $parents[$category->getParentId()] = true;
        }

        if (count($parents) <= 1) {
            usort($categories, static function (rex_category $a, rex_category $b) {
                return $a->getPriority() <=> $b->getPriority();
            });
        } else {
            usort($categories, static function (rex_category $a, rex_category $b) {
                return strcasecmp($a->getName(), $b->getName());
            });
        }

        return $categories;
    }

    /** @return array */
    public static function getFieldParams()
    {
        return [
            'label' => rex_i18n::msg('categories'),
            'all_label' => rex_i18n::msg('all_categories'),
            'select' => new rex_category_select(false, false, false, false),
        ];
    }
}
