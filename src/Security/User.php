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

class User
{
    use InstancePoolTrait {
        clearInstance as baseClearInstance;
    }

    /**
     * SQL instance.
     *
     * @var Sql
     */
    protected $sql;

    /** @var bool|null */
    private $admin;

    /**
     * User role instance.
     *
     * @var UserRoleInterface|null
     */
    protected $role;

    /**
     * Class name for user roles.
     *
     * @var class-string<UserRoleInterface>
     */
    protected static $roleClass;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public static function get(int $id): ?self
    {
        return static::getInstance($id, static function () use ($id) {
            $sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('user') . ' WHERE id = ?', [$id]);

            if ($sql->getRows()) {
                $user = new static($sql);
                static::addInstance('login_' . $user->getLogin(), $user);
                return $user;
            }

            return null;
        });
    }

    public static function forLogin(#[SensitiveParameter] string $login): ?self
    {
        return static::getInstance('login_' . $login, static function () use ($login) {
            $sql = Sql::factory()->setQuery('SELECT * FROM ' . Core::getTable('user') . ' WHERE login = ?', [$login]);

            if ($sql->getRows()) {
                $user = new static($sql);
                static::addInstance($user->getId(), $user);
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

    public static function fromSql(Sql $sql): self
    {
        $user = new self($sql);
        self::addInstance($user->getId(), $user);
        self::addInstance('login_' . $user->getLogin(), $user);

        return $user;
    }

    /**
     * Returns the value for the given key.
     *
     * @param string $key Key
     *
     * @return string value
     */
    public function getValue($key)
    {
        return $this->sql->getValue($key);
    }

    /**
     * Returns the ID.
     *
     * @return int
     */
    public function getId()
    {
        return (int) $this->sql->getValue('id');
    }

    /**
     * Returns the user login.
     *
     * @return string Login
     */
    public function getLogin()
    {
        return $this->sql->getValue('login');
    }

    /**
     * Returns the name.
     *
     * @return string Name
     */
    public function getName()
    {
        return $this->sql->getValue('name');
    }

    /**
     * Returns the email.
     *
     * @return string email
     */
    public function getEmail()
    {
        return $this->sql->getValue('email');
    }

    /**
     * Returns if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        if (null === $this->admin) {
            $this->admin = (bool) $this->sql->getValue('admin');
        }

        return $this->admin;
    }

    /**
     * Returns the language.
     *
     * @return string|null Language
     */
    public function getLanguage()
    {
        return $this->sql->getValue('language');
    }

    /**
     * Returns the start page.
     *
     * @return string|null Start page
     */
    public function getStartPage()
    {
        return $this->sql->getValue('startpage');
    }

    /**
     * Returns if the user has a role.
     *
     * @return bool
     */
    public function hasRole()
    {
        if (self::$roleClass && !is_object($this->role) && ($role = $this->sql->getValue('role'))) {
            $class = self::$roleClass;
            $this->role = $class::get($role);
        }
        return is_object($this->role);
    }

    /**
     * Returns if the user has the given permission.
     *
     * @param string $perm Perm key
     *
     * @return bool
     */
    public function hasPerm($perm)
    {
        if ($this->isAdmin()) {
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
            return $this->isAdmin();
        }
        return $result;
    }

    /**
     * Returns the complex perm for the user.
     *
     * @param string $key Complex perm key
     *
     * @return ComplexPermission|null Complex perm
     * @phpstan-return MediaPoolPermission|StructurePermission|ModulePermission|LanguagePermission|null
     * @psalm-return ComplexPermission|null
     */
    public function getComplexPerm($key)
    {
        if ($this->hasRole()) {
            return $this->role->getComplexPerm($this, $key);
        }
        return ComplexPermission::get($this, $key);
    }

    /**
     * Sets the role class.
     *
     * @param class-string<UserRoleInterface> $class Class name
     * @return void
     */
    public static function setRoleClass($class)
    {
        self::$roleClass = $class;
    }

    /** Removes the instance of the given key. */
    public static function clearInstance(int|string $key): void
    {
        $user = static::getInstance($key);

        if (!$user) {
            return;
        }

        static::baseClearInstance($user->getId());
        static::baseClearInstance('login_' . $user->getLogin());
    }
}
