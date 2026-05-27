<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

/** @extends ComplexPermission<int> */
final class LanguagePermission extends ComplexPermission
{
    public function hasPerm(int $clangId): bool
    {
        return $this->hasAll() || in_array($clangId, $this->perms);
    }

    public function count(): int
    {
        return $this->hasAll() ? Language::count() : count($this->perms);
    }

    /** @return list<int> */
    public function getClangs(): array
    {
        return $this->hasAll() ? Language::getAllIds() : $this->perms;
    }

    public static function getFieldParams(): array
    {
        $options = array_map(static function (Language $clang) {
            return $clang->name;
        }, Language::getAll());

        return [
            'label' => I18n::msg('clangs'),
            'all_label' => I18n::msg('all_clangs'),
            'options' => $options,
        ];
    }
}
