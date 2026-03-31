<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

class LanguagePermission extends ComplexPermission
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
        return $this->hasAll() ? Language::count() : count($this->perms);
    }

    /** @return array */
    public function getClangs()
    {
        return $this->hasAll() ? Language::getAllIds() : $this->perms;
    }

    public static function getFieldParams()
    {
        $options = array_map(static function (Language $clang) {
            return $clang->getName();
        }, Language::getAll());

        return [
            'label' => I18n::msg('clangs'),
            'all_label' => I18n::msg('all_clangs'),
            'options' => $options,
        ];
    }
}
