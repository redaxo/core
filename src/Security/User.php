<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Language\LanguagePermission;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use SensitiveParameter;

use function in_array;
use function is_object;
use function sprintf;

final class User
{
    use InstancePoolTrait {
        clearInstance as baseClearInstance;
    }

    public int $id {
        get => $this->sql->getValue('id');
    }

    public string $login {
        get => $this->sql->getValue('login');
    }

    public ?string $name {
        get => $this->sql->getValue('name');
    }

    public ?string $email {
        get => $this->sql->getValue('email');
    }

    public bool $admin {
        get => (bool) $this->sql->getValue('admin');
    }

    public ?string $password {
        get => $this->sql->getValue('password');
    }

    public ?string $language {
        get => $this->sql->getValue('language');
    }

    public ?string $startPage {
        get => $this->sql->getValue('startpage');
    }

    public ?string $theme {
        get => $this->sql->getValue('theme');
    }

    private ?UserRole $role = null;

    private function __construct(
        private readonly Sql $sql,
    ) {}

    public static function get(int $id): ?self
    {
        return self::getInstance($id, static function () use ($id): ?self {
            $sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('user') . ' WHERE id = ?', [$id]);

            if ($sql->getRows()) {
                $user = new static($sql);
                self::addInstance('login_' . $user->login, $user);
                return $user;
            }

            return null;
        });
    }

    public static function forLogin(#[SensitiveParameter] string $login): ?self
    {
        return self::getInstance('login_' . $login, static function () use ($login): ?self {
            $sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('user') . ' WHERE login = ?', [$login]);

            if ($sql->getRows()) {
                $user = new static($sql);
                self::addInstance($user->id, $user);
                return $user;
            }

            return null;
        });
    }

    public static function require(int $id): self
    {
        $user = self::get($id);

        if (!$user) {
            throw new RuntimeException(sprintf('Required user with id %d does not exist.', $id));
        }

        return $user;
    }

    /** @internal */
    public static function fromSql(Sql $sql): self
    {
        $user = new self($sql);
        self::addInstance($user->id, $user);
        self::addInstance('login_' . $user->login, $user);

        return $user;
    }

    /** Returns the value for the given key. */
    public function getValue(string $key): string|int|null
    {
        /** @var string|int|null */
        return $this->sql->getValue($key);
    }

    /** Returns if the user has a role. */
    public function hasRole(): bool
    {
        if (!is_object($this->role) && ($role = $this->sql->getValue('role'))) {
            $this->role = UserRole::get($role);
        }
        return is_object($this->role);
    }

    /** Returns if the user has the given permission. */
    public function hasPerm(string $perm): bool
    {
        if ($this->admin) {
            return true;
        }
        $result = false;
        if (str_contains($perm, '/')) {
            [$complexPerm, $method] = explode('/', $perm, 2);
            $complexPerm = $this->getComplexPerm($complexPerm);
            return $complexPerm ? $complexPerm->$method() : false;
        }
        if ($this->hasRole()) {
            $result = $this->role->hasPerm($perm);
        }
        if (!$result && in_array($perm, ['isAdmin', 'admin', 'admin[]'])) {
            return $this->admin;
        }
        return $result;
    }

    /**
     * Returns the complex perm for the user.
     *
     * @param string $key Complex perm key
     * @phpstan-return (
     *      $key is 'media' ? MediaPoolPermission :
     *      ($key is 'structure' ? StructurePermission :
     *      ($key is 'modules' ? ModulePermission :
     *      ($key is 'clang' ? LanguagePermission :
     *      ComplexPermission|null
     *      ))))
     *  )
     */
    public function getComplexPerm(string $key): ?ComplexPermission
    {
        if ($this->hasRole()) {
            return $this->role->getComplexPerm($this, $key);
        }
        return ComplexPermission::get($this, $key);
    }

    /** Removes the instance of the given key. */
    public static function clearInstance(int|string $key): void
    {
        $user = self::getInstance($key);

        if (!$user) {
            return;
        }

        self::baseClearInstance($user->id);
        self::baseClearInstance('login_' . $user->login);
    }
}
