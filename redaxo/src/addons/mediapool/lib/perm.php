<?php

/**
 * @package redaxo\mediapool
 */
class rex_media_perm extends rex_complex_perm
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
            'label' => rex_i18n::msg('mediafolder'),
            'all_label' => rex_i18n::msg('all_mediafolder'),
            'select' => new rex_media_category_select(false),
        ];
    }
}
