<?php

namespace Redaxo\Core\Content;

use Collator;
use Locale;
use Override;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Security\PermissionChoices;
use Redaxo\Core\Translation\Translatable;

use function in_array;

/** @extends ComplexPermission<string> */
final class ModulePermission extends ComplexPermission
{
    public function hasPerm(string $moduleKey): bool
    {
        return $this->hasAll() || in_array($moduleKey, $this->perms);
    }

    #[Override]
    public static function getChoices(): PermissionChoices
    {
        $choices = [];
        foreach (Module::getAll() as $key => $module) {
            $choices[$key] = $module::name();
        }
        $collator = new Collator(Locale::getDefault());
        uasort($choices, static fn (string $a, string $b): int => (int) $collator->compare($a, $b));

        return new PermissionChoices(new Translatable('modules'), new Translatable('all_modules'), $choices);
    }
}
