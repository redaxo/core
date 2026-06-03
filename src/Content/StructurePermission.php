<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Form\Select\CategorySelect;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

/** @extends ComplexPermission<int> */
final class StructurePermission extends ComplexPermission
{
    public function hasCategoryPerm(?int $categoryId): bool
    {
        if ($this->hasAll()) {
            return true;
        }
        if (null === $categoryId) {
            return false;
        }
        if (in_array($categoryId, $this->perms, true)) {
            return true;
        }
        if ($c = Category::get($categoryId)) {
            $perms = $this->perms;
            return array_any($c->path, static fn (int $k) => in_array($k, $perms, true));
        }
        return false;
    }

    public function hasStructurePerm(): bool
    {
        return $this->hasAll() || count($this->perms) > 0;
    }

    /** @return list<int> */
    public function getMountpoints(): array
    {
        return $this->hasAll() ? [] : $this->perms;
    }

    public function hasMountpoints(): bool
    {
        return !$this->hasAll() && count($this->perms) > 0;
    }

    /** @return list<Category> */
    public function getMountpointCategories(): array
    {
        if ($this->hasAll()) {
            return [];
        }

        $categories = [];
        $parents = [];
        foreach ($this->perms as $id) {
            $category = Category::get($id);
            if (!$category) {
                continue;
            }

            $categories[] = $category;
            $parents[$category->parentId ?? 0] = true;
        }

        if (count($parents) <= 1) {
            usort($categories, static function (Category $a, Category $b) {
                return $a->priority <=> $b->priority;
            });
        } else {
            usort($categories, static function (Category $a, Category $b) {
                return strcasecmp($a->name, $b->name);
            });
        }

        return $categories;
    }

    public static function getFieldParams(): array
    {
        return [
            'label' => I18n::msg('categories'),
            'all_label' => I18n::msg('all_categories'),
            'select' => new CategorySelect(false, null, false, false),
        ];
    }
}
