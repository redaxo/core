<?php

/**
 * Class for users.
 *
 * @author gharlan
 *
 * @package redaxo\core\login
 */
class rex_user
{
    use rex_instance_pool_trait {
        clearInstance as baseClearInstance;
    }

    /**
     * SQL instance.
     *
     * @var rex_sql
     */
    protected $sql;

    /** @var bool|null */
    private $admin;

    /**
     * User role instance.
     *
     * @var rex_user_role_interface|null
     */
    protected $role;

    /**
     * Class name for user roles.
     *
     * @var class-string<rex_user_role_interface>
     */
    protected static $roleClass;

    public function __construct(rex_sql $sql)
    {
        $this->sql = $sql;
    }

    public static function get(int $id): ?self
    {
        return static::getInstance($id, static function (int $id) {
            $sql = rex_sql::factory()->setQuery('SELECT * FROM ' . rex::getTable('user') . ' WHERE id = ?', [$id]);

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
            $sql = rex_sql::factory()->setQuery('SELECT * FROM ' . rex::getTable('user') . ' WHERE login = ?', [$login]);

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

    public static function fromSql(rex_sql $sql): self
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
     * @return string Language
     */
    public function getLanguage()
    {
        return $this->sql->getValue('language');
    }

    /**
     * Returns the start page.
     *
     * @return string Start page
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
     * @return rex_complex_perm|null Complex perm
     * @phpstan-return (
     *      $key is 'media' ? rex_media_perm :
     *      ($key is 'structure' ? rex_structure_perm :
     *      ($key is 'modules' ? rex_module_perm :
     *      ($key is 'clang' ? rex_clang_perm :
     *      rex_complex_perm|null
     *      ))))
     *  )
     */
    public function getComplexPerm($key)
    {
        if ($this->hasRole()) {
            return $this->role->getComplexPerm($this, $key);
        }
        return rex_complex_perm::get($this, $key);
    }

    /**
     * Sets the role class.
     *
     * @param class-string<rex_user_role_interface> $class Class name
     * @return void
     */
    public static function setRoleClass($class)
    {
        self::$roleClass = $class;
    }

    /**
     * Removes the instance of the given key.
     *
     * @param mixed $key Key
     * @return void
     */
    public static function clearInstance($key)
    {
        $user = static::getInstance($key);

        if (!$user) {
            return;
        }

        static::baseClearInstance($user->getId());
        static::baseClearInstance('login_' . $user->getLogin());
    }
}
