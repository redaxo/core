<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

/** @extends ComplexPermission<int> */
final class MediaPoolPermission extends ComplexPermission
{
    public function hasCategoryPerm(int $categoryId): bool
    {
        return $this->hasAll() || in_array($categoryId, $this->perms);
    }

    public function hasMediaPerm(): bool
    {
        return $this->hasAll() || count($this->perms) > 0;
    }

    public static function getFieldParams(): array
    {
        return [
            'label' => I18n::msg('mediafolder'),
            'all_label' => I18n::msg('all_mediafolder'),
            'select' => new MediaCategorySelect(false),
        ];
    }
}
