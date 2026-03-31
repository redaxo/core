<?php

namespace Redaxo\Core\Base;

use Redaxo\Core\Exception\InvalidArgumentException;

/**
 * Factory trait.
 *
 * Example child class:
 * <code>
 * class Example
 * {
 *     use FactoryTrait;
 *
 *     private function __construct($param)
 *     {
 *         // ...
 *     }
 *
 *     public static function factory($param)
 *     {
 *         $class = self::getFactoryClass();
 *         return new $class($param);
 *     }
 * }
 * </code>
 */
trait FactoryTrait
{
    /** @var array<class-string<static>, class-string<static>> */
    private static array $factoryClasses = [];

    /**
     * Sets the class for the factory.
     *
     * @phpstan-param class-string<static> $subClass
     * @psalm-param class-string<self> $subClass https://github.com/vimeo/psalm/issues/5535
     */
    public static function setFactoryClass(string $subClass): void
    {
        $calledClass = static::class;
        if ($subClass !== $calledClass && !is_subclass_of($subClass, $calledClass)) {
            throw new InvalidArgumentException('Factory class "' . $subClass . '" is expected to define a subclass of ' . $calledClass . '.');
        }

        self::$factoryClasses[$calledClass] = $subClass;
    }

    /**
     * Returns the class for the factory. In case no factory is defined the late static binding class is returned.
     *
     * @return class-string<static>
     */
    public static function getFactoryClass(): string
    {
        return self::$factoryClasses[static::class] ?? static::class;
    }

    /**
     * Returns the explicitly set factory class, otherwise null.
     *
     * @return class-string<static>|null
     */
    public static function getExplicitFactoryClass(): ?string
    {
        return self::$factoryClasses[static::class] ?? null;
    }

    /** Returns if the class has a custom factory class. */
    public static function hasFactoryClass(): bool
    {
        return isset(self::$factoryClasses[static::class]);
    }
}
