<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Translation\I18n;

final class Permission
{
    public const string GENERAL = 'general';
    public const string OPTIONS = 'options';
    public const string EXTRAS = 'extras';

    /**
     * Array of permissions.
     *
     * @var array<self::*, array<string, string>>
     */
    private static array $perms = [];

    private function __construct() {}

    /**
     * Registers a new permission.
     *
     * @param string $perm Perm key
     * @param string|null $name Perm name
     * @param self::* $group Perm group, possible values are Permission::GENERAL, Permission::OPTIONS and Permission::EXTRAS
     */
    public static function register(string $perm, ?string $name = null, string $group = self::GENERAL): void
    {
        if ($name) {
            $name = $perm . ' :: ' . $name;
        } else {
            $name = (I18n::hasMsg($key = 'perm_' . $group . '_' . $perm) ? $perm . ' :: ' . I18n::rawMsg($key) : $perm);
        }

        self::$perms[$group][$perm] = $name;
    }

    /** Returns whether the permission is registered. */
    public static function has(string $perm): bool
    {
        return array_any(self::$perms, static fn ($perms) => isset($perms[$perm]));
    }

    /**
     * Returns all permissions for the given group.
     *
     * @param self::* $group Perm group
     *
     * @return array<string, string> Permissions
     */
    public static function getAll(string $group = self::GENERAL): array
    {
        if (isset(self::$perms[$group])) {
            $perms = self::$perms[$group];
            natcasesort($perms);
            return $perms;
        }
        return [];
    }
}
