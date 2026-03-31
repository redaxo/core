<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

class MediaPoolPermission extends ComplexPermission
{
    /**
     * @param int $categoryId
     * @return bool
     */
    public function hasCategoryPerm($categoryId)
    {
        return $this->hasAll() || in_array($categoryId, $this->perms);
    }

    /** @return bool */
    public function hasMediaPerm()
    {
        return $this->hasAll() || count($this->perms) > 0;
    }

    public static function getFieldParams()
    {
        return [
            'label' => I18n::msg('mediafolder'),
            'all_label' => I18n::msg('all_mediafolder'),
            'select' => new MediaCategorySelect(false),
        ];
    }
}
