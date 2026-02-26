<?php

/**
 * Class for user roles.
 *
 * @author gharlan
 *
 * @package redaxo\users
 */
class rex_user_role implements rex_user_role_interface
{
    /**
     * Permissions.
     *
     * @var list<string>
     */
    private array $perms = [];

    /**
     * Complex perm params.
     *
     * @var array<string, rex_complex_perm::ALL|array<string>>
     */
    private array $complexPermParams = [];

    /**
     * Cache for complex perm instances.
     *
     * @var array<string, rex_complex_perm|null>
     */
    private array $complexPerms = [];

    /** @param list<array> $roles */
    private function __construct(array $roles)
    {
        foreach ($roles as $role) {
            foreach ([rex_perm::GENERAL, rex_perm::OPTIONS, rex_perm::EXTRAS] as $key) {
                $perms = $role[$key] ? explode('|', trim($role[$key], '|')) : [];
                $this->perms = array_merge($this->perms, $perms);
                unset($role[$key]);
            }

            foreach ($role as $key => $value) {
                if (rex_complex_perm::ALL === $role[$key]) {
                    $perms = rex_complex_perm::ALL;
                } else {
                    $perms = $role[$key] ? explode('|', trim($role[$key], '|')) : [];
                    if (1 == count($perms) && '' == $perms[0]) {
                        $perms = [];
                    }
                }

                if (!isset($this->complexPermParams[$key])) {
                    $this->complexPermParams[$key] = $perms;
                } elseif (rex_complex_perm::ALL == $this->complexPermParams[$key]) {
                } elseif (rex_complex_perm::ALL == $perms) {
                    $this->complexPermParams[$key] = $perms;
                } else {
                    $this->complexPermParams[$key] = array_merge($perms, $this->complexPermParams[$key]);
                }
            }
        }
    }

    public function hasPerm($perm)
    {
        return in_array($perm, $this->perms);
    }

    public function getComplexPerm(rex_user $user, $key)
    {
        if (isset($this->complexPerms[$key])) {
            return $this->complexPerms[$key];
        }

        if (!isset($this->complexPermParams[$key])) {
            $this->complexPermParams[$key] = [];
        } elseif (rex_complex_perm::ALL !== $this->complexPermParams[$key]) {
            $this->complexPermParams[$key] = array_unique($this->complexPermParams[$key]);
        }

        $this->complexPerms[$key] = rex_complex_perm::get($user, $key, $this->complexPermParams[$key]);
        return $this->complexPerms[$key];
    }

    public static function get($ids)
    {
        $sql = rex_sql::factory();
        $userRoles = $sql->getArray('SELECT perms FROM ' . rex::getTablePrefix() . 'user_role WHERE FIND_IN_SET(id, ?)', [$ids]);
        if (0 == count($userRoles)) {
            return null;
        }

        $roles = [];
        foreach ($userRoles as $userRole) {
            $roles[] = json_decode((string) $userRole['perms'], true);
        }

        return new static($roles);
    }

    /** @return void */
    public static function removeOrReplaceItem(rex_extension_point $ep)
    {
        $params = $ep->getParams();
        $key = $params['key'];
        $item = '|' . $params['item'] . '|';
        $new = isset($params['new']) ? '|' . $params['new'] . '|' : '|';
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id, perms FROM ' . rex::getTable('user_role'));
        $update = rex_sql::factory();
        $update->prepareQuery('UPDATE ' . rex::getTable('user_role') . ' SET perms = ? WHERE id = ?');
        foreach ($sql as $row) {
            $perms = $row->getArrayValue('perms');
            if (isset($perms[$key]) && str_contains($perms[$key], $item)) {
                $perms[$key] = str_replace($item, $new, $perms[$key]);
                $update->execute([json_encode($perms), $row->getValue('id')]);
            }
        }
    }
}
