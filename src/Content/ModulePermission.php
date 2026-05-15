<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;

use function in_array;

/** @extends ComplexPermission<int> */
final class ModulePermission extends ComplexPermission
{
    public function hasPerm(int $moduleId): bool
    {
        return $this->hasAll() || in_array($moduleId, $this->perms);
    }

    public static function getFieldParams(): array
    {
        return [
            'label' => I18n::msg('modules'),
            'all_label' => I18n::msg('all_modules'),
            'sql_options' => 'select name, id from ' . Core::getTablePrefix() . 'module order by name',
        ];
    }
}
