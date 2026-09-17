<?php

/**
 * Instance Pool Trait.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
trait rex_instance_pool_trait
{
    /** @var array<class-string, array<string, static|null>> */
    private static array $instances = [];

    private function __construct()
    {
        // noop
    }

    /**
     * Adds an instance.
     *
     * @param mixed $key Key
     * @param self $instance Instance
     * @return void
     */
    protected static function addInstance($key, self $instance)
    {
        $key = self::getInstancePoolKey($key);
        $class = static::class;
        self::$instances[$class][$key] = $instance;
    }

    /**
     * Checks whether an instance exists for the given key.
     *
     * @param mixed $key Key
     *
     * @return bool
     */
    protected static function hasInstance($key)
    {
        $key = self::getInstancePoolKey($key);
        $class = static::class;
        return isset(self::$instances[$class][$key]);
    }

    /**
     * Returns the instance for the given key.
     *
     * If the instance does not exist it will be created by calling the $createCallback
     *
     * @param mixed $key Key
     * @param callable(mixed...):?static $createCallback Callback, will be called to create a new instance
     *
     * @return static|null
     */
    protected static function getInstance($key, ?callable $createCallback = null)
    {
        $args = (array) $key;
        $key = self::getInstancePoolKey($args);
        $class = static::class;
        if ($createCallback && !array_key_exists($key, self::$instances[$class] ?? [])) {
            $instance = call_user_func_array($createCallback, $args);
            self::$instances[$class][$key] = $instance instanceof static ? $instance : null;
        }
        return self::$instances[$class][$key] ?? null;
    }

    /**
     * Removes the instance of the given key.
     *
     * @param mixed $key Key
     * @return void
     */
    public static function clearInstance($key)
    {
        $key = self::getInstancePoolKey($key);
        $class = static::class;
        unset(self::$instances[$class][$key]);
    }

    /**
     * Clears the instance pool.
     * @return void
     */
    public static function clearInstancePool()
    {
        $calledClass = static::class;
        // unset instances of calledClass and of all subclasses of calledClass
        foreach (self::$instances as $class => $_) {
            if ($class === $calledClass || is_subclass_of($class, $calledClass)) {
                unset(self::$instances[$class]);
            }
        }
    }

    /**
     * Returns a string representation for the key.
     *
     * The original key can be a scalar value or an array of scalar values
     *
     * @param mixed $key Key
     *
     * @return string
     */
    private static function getInstancePoolKey($key)
    {
        return implode('###', (array) $key);
    }
}
