<?php

namespace Redaxo\Core\Base;

use function is_array;

/**
 * @psalm-type TKey = int|string|list<int|string>
 */
trait InstanceListPoolTrait
{
    /** @var array<string, list<TKey>> */
    private static array $instanceLists = [];

    /**
     * Adds an instance list.
     *
     * @param TKey $key
     * @param list<TKey> $instanceKeyList Array of instance keys
     */
    protected static function addInstanceList(int|string|array $key, array $instanceKeyList): void
    {
        $key = self::getInstanceListPoolKey($key);
        self::$instanceLists[$key] = $instanceKeyList;
    }

    /**
     * Checks whether an instance list exists for the given key.
     *
     * @param TKey $key
     */
    protected static function hasInstanceList(int|string|array $key): bool
    {
        $key = self::getInstanceListPoolKey($key);
        return isset(self::$instanceLists[$key]);
    }

    /**
     * Returns the instance list for the given key.
     *
     * If the instance list does not exist it will be created by calling the $createListCallback
     *
     * @template TInstance as object
     * @template TInstanceKey as int|string|list<int|string>
     * @param TKey $key
     * @param callable(TInstanceKey):(TInstance|null) $getInstanceCallback Callback, will be called for every list item to get the instance
     * @param callable():list<TInstanceKey>|null $createListCallback Callback, will be called to create the list of instance keys
     * @return list<TInstance>
     */
    protected static function getInstanceList(int|string|array $key, callable $getInstanceCallback, ?callable $createListCallback = null): array
    {
        $args = (array) $key;
        $key = self::getInstanceListPoolKey($args);

        if (!isset(self::$instanceLists[$key]) && $createListCallback) {
            $list = $createListCallback();
            self::$instanceLists[$key] = is_array($list) ? $list : [];
        }

        if (!isset(self::$instanceLists[$key])) {
            return [];
        }

        $list = [];
        foreach (self::$instanceLists[$key] as $instanceKey) {
            /** @psalm-suppress PossiblyInvalidArgument */
            $instance = $getInstanceCallback($instanceKey);
            if ($instance) {
                $list[] = $instance;
            }
        }

        return $list;
    }

    /**
     * Clears the instance list of the given key.
     *
     * @param TKey $key
     */
    public static function clearInstanceList(int|string|array $key): void
    {
        $key = self::getInstanceListPoolKey($key);
        unset(self::$instanceLists[$key]);
    }

    /** Clears the instance list pool. */
    public static function clearInstanceListPool(): void
    {
        self::$instanceLists = [];
    }

    /**
     * Returns a string representation for the key.
     *
     * The original key can be a scalar value or an array of scalar values
     *
     * @param TKey $key
     */
    private static function getInstanceListPoolKey(int|string|array $key): string
    {
        return implode('###', (array) $key);
    }
}
