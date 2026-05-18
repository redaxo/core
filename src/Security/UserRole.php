<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

use function count;
use function in_array;

/**
 * @internal
 */
final class UserRole
{
    /** @var list<string> */
    private array $perms = [];

    /** @var array<string, ComplexPermission::ALL|list<string>> */
    private array $complexPermParams = [];

    /** @var array<string, ComplexPermission|null> */
    private array $complexPerms = [];

    /** @param list<array<string, string>> $roles */
    private function __construct(array $roles)
    {
        foreach ($roles as $role) {
            foreach ([Permission::GENERAL, Permission::OPTIONS, Permission::EXTRAS] as $key) {
                $perms = $role[$key] ? explode('|', trim($role[$key], '|')) : [];
                $this->perms = array_merge($this->perms, $perms);
                unset($role[$key]);
            }

            foreach ($role as $key => $value) {
                if (ComplexPermission::ALL === $role[$key]) {
                    $perms = ComplexPermission::ALL;
                } else {
                    $perms = $role[$key] ? explode('|', trim($role[$key], '|')) : [];
                    if (1 == count($perms) && '' == $perms[0]) {
                        $perms = [];
                    }
                }

                if (!isset($this->complexPermParams[$key])) {
                    $this->complexPermParams[$key] = $perms;
                } elseif (ComplexPermission::ALL == $this->complexPermParams[$key]) {
                } elseif (ComplexPermission::ALL == $perms) {
                    $this->complexPermParams[$key] = $perms;
                } else {
                    $this->complexPermParams[$key] = array_merge($perms, $this->complexPermParams[$key]);
                }
            }
        }
    }

    /** Returns if the role has the given permission. */
    public function hasPerm(string $perm): bool
    {
        return in_array($perm, $this->perms);
    }

    public function getComplexPerm(User $user, string $key): ?ComplexPermission
    {
        if (isset($this->complexPerms[$key])) {
            return $this->complexPerms[$key];
        }

        if (!isset($this->complexPermParams[$key])) {
            $this->complexPermParams[$key] = [];
        } elseif (ComplexPermission::ALL !== $this->complexPermParams[$key]) {
            $this->complexPermParams[$key] = array_values(array_unique($this->complexPermParams[$key]));
        }

        $this->complexPerms[$key] = ComplexPermission::get($user, $key, $this->complexPermParams[$key]);
        return $this->complexPerms[$key];
    }

    /**
     * Returns the role for the given ID.
     *
     * @param string $ids Comma separated role IDs
     */
    public static function get(string $ids): ?self
    {
        $sql = Sql::factory();
        $userRoles = $sql->getArray('SELECT perms FROM ' . Core::getTablePrefix() . 'user_role WHERE FIND_IN_SET(id, ?)', [$ids]);
        if (0 == count($userRoles)) {
            return null;
        }

        $roles = [];
        foreach ($userRoles as $userRole) {
            $roles[] = json_decode((string) $userRole['perms'], true);
        }

        return new self($roles);
    }

    #[AsExtension('COMPLEX_PERM_REMOVE_ITEM')]
    #[AsExtension('COMPLEX_PERM_REPLACE_ITEM')]
    public static function removeOrReplaceItem(ExtensionPoint $ep): void
    {
        $params = $ep->getParams();
        $key = $params['key'];
        $item = '|' . $params['item'] . '|';
        $new = isset($params['new']) ? '|' . $params['new'] . '|' : '|';
        $sql = Sql::factory();
        $sql->setQuery('SELECT id, perms FROM ' . Core::getTable('user_role'));
        $update = Sql::factory();
        $update->prepareQuery('UPDATE ' . Core::getTable('user_role') . ' SET perms = ? WHERE id = ?');
        foreach ($sql as $row) {
            $perms = $row->getArrayValue('perms');
            if (isset($perms[$key]) && str_contains($perms[$key], $item)) {
                $perms[$key] = str_replace($item, $new, $perms[$key]);
                $update->execute([json_encode($perms), $row->getValue('id')]);
            }
        }
    }
}
