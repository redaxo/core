<?php

/**
 * @package redaxo\core
 */
class rex_clang_perm extends rex_complex_perm
{
    /**
     * @param int $clangId
     *
     * @return bool
     */
    public function hasPerm($clangId)
    {
        return $this->hasAll() || in_array($clangId, $this->perms);
    }

    /** @return int */
    public function count()
    {
        return $this->hasAll() ? rex_clang::count() : count($this->perms);
    }

    /** @return array */
    public function getClangs()
    {
        return $this->hasAll() ? rex_clang::getAllIds() : $this->perms;
    }

    public static function getFieldParams()
    {
        $options = array_map(static function (rex_clang $clang) {
            return $clang->getName();
        }, rex_clang::getAll());

        return [
            'label' => rex_i18n::msg('clangs'),
            'all_label' => rex_i18n::msg('all_clangs'),
            'options' => $options,
        ];
    }
}
