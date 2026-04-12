<?php

namespace Redaxo\Core\Content;

use Collator;
use Locale;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function in_array;

/** @extends ComplexPermission<string> */
final class ModulePermission extends ComplexPermission
{
    public function hasPerm(string $moduleKey): bool
    {
        return $this->hasAll() || in_array($moduleKey, $this->perms);
    }

    public static function getFieldParams(): array
    {
        $options = [];
        foreach (Module::getAll() as $module) {
            $options[$module->key] = I18n::translate($module->name);
        }
        new Collator(Locale::getDefault())->asort($options);

        return [
            'label' => I18n::msg('modules'),
            'all_label' => I18n::msg('all_modules'),
            'options' => $options,
        ];
    }
}
