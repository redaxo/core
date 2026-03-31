<?php

namespace Redaxo\Core\Base;

use Redaxo\Core\Util\Type;

/**
 * @psalm-type TKey = int|string|list<int|string>
 */
trait InstancePoolTrait
{
    /** @var array<class-string<self>, array<string, static|null>> */
    private static array $instances = [];

    private function __construct() {}

    /**
     * Adds an instance.
     *
     * @param TKey $key
     */
    protected static function addInstance(int|string|array $key, self $instance): void
    {
        $key = self::getInstancePoolKey($key);
        $class = static::class;
        /** @psalm-suppress PropertyTypeCoercion https://github.com/vimeo/psalm/issues/10835 */
        self::$instances[$class][$key] = Type::instanceOf($instance, $class);
    }

    /**
     * Checks whether an instance exists for the given key.
     *
     * @param TKey $key
     */
    protected static function hasInstance(int|string|array $key): bool
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
     * @param TKey $key
     * @param callable():(static|null)|null $createCallback Callback, will be called to create a new instance
     */
    protected static function getInstance(int|string|array $key, ?callable $createCallback = null): ?static
    {
        $args = (array) $key;
        $key = self::getInstancePoolKey($args);
        $class = static::class;
        if (!isset(self::$instances[$class][$key]) && $createCallback) {
            $instance = $createCallback();
            /** @psalm-suppress PropertyTypeCoercion https://github.com/vimeo/psalm/issues/10835 */
            self::$instances[$class][$key] = $instance instanceof static ? $instance : null;
        }
        return self::$instances[$class][$key] ?? null;
    }

    /**
     * Removes the instance of the given key.
     *
     * @param TKey $key
     */
    public static function clearInstance(int|string|array $key): void
    {
        $key = self::getInstancePoolKey($key);
        $class = static::class;
        unset(self::$instances[$class][$key]);
    }

    /** Clears the instance pool. */
    public static function clearInstancePool(): void
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
     * @param TKey $key
     */
    private static function getInstancePoolKey(int|string|array $key): string
    {
        return implode('###', (array) $key);
    }
}
