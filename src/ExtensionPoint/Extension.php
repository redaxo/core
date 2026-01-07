<?php

namespace Redaxo\Core\ExtensionPoint;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Util\Timer;

use function call_user_func;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

use const E_USER_WARNING;

/**
 * Klasse die Einsprungpunkte zur Erweiterung der Kernfunktionalitaet bietet.
 */
abstract class Extension
{
    use FactoryTrait;

    public const EARLY = -1;
    public const NORMAL = 0;
    public const LATE = 1;

    /**
     * Array of registered extensions.
     *
     * @var array<string, array<self::*, list<array{callable, array<string, mixed>}>>>
     */
    private static array $extensions = [];

    /**
     * Registers an extension point.
     *
     * @template T
     * @param ExtensionPoint<T> $extensionPoint Extension point
     * @return T Subject, maybe adjusted by the extensions
     *
     * @psalm-taint-specialize
     */
    public static function registerPoint(ExtensionPoint $extensionPoint)
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::registerPoint($extensionPoint);
        }

        $name = $extensionPoint->getName();

        Timer::measure('EP: ' . $name, static function () use ($extensionPoint, $name) {
            foreach ([self::EARLY, self::NORMAL, self::LATE] as $level) {
                if (!isset(self::$extensions[$name][$level]) || !is_array(self::$extensions[$name][$level])) {
                    continue;
                }

                foreach (self::$extensions[$name][$level] as $extensionAndParams) {
                    [$extension, $params] = $extensionAndParams;
                    $extensionPoint->setExtensionParams($params);
                    /** @var T|null $subject */
                    $subject = call_user_func($extension, $extensionPoint);
                    // Update subject only if the EP is not readonly and the extension has returned something
                    if ($extensionPoint->isReadonly()) {
                        continue;
                    }
                    if (null === $subject) {
                        continue;
                    }
                    $extensionPoint->setSubject($subject);
                }
            }
        });

        return $extensionPoint->getSubject();
    }

    /**
     * Registers an extension for an extension point.
     *
     * @template T as ExtensionPoint
     * @param string|list<string> $extensionPoint Name(s) of extension point(s)
     * @param callable(T):mixed $extension Callback extension
     * @param self::* $level Runlevel (`Extension::EARLY`, `Extension::NORMAL` or `Extension::LATE`)
     * @param array<string, mixed> $params Additional params
     * @return void
     */
    public static function register($extensionPoint, callable $extension, $level = self::NORMAL, array $params = [])
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::register($extensionPoint, $extension, $level, $params);
            return;
        }

        // bc
        if (is_string($level)) {
            trigger_error(__METHOD__ . ': Argument $level should be one of the constants ' . self::class . '::EARLY/NORMAL/LATE, but string "' . $level . '" given', E_USER_WARNING);

            $level = (int) $level;
        }

        if (!in_array($level, [self::EARLY, self::NORMAL, self::LATE], true)) {
            throw new InvalidArgumentException('Argument $level should be one of the constants ' . self::class . '::EARLY/NORMAL/LATE, but "' . (is_int($level) ? $level : get_debug_type($level)) . '" given');
        }

        foreach ((array) $extensionPoint as $ep) {
            self::$extensions[$ep][$level][] = [$extension, $params];
        }
    }

    /**
     * Checks whether an extension is registered for the given extension point.
     *
     * @param string $extensionPoint Name of extension point
     *
     * @return bool
     */
    public static function isRegistered($extensionPoint)
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::isRegistered($extensionPoint);
        }
        return !empty(self::$extensions[$extensionPoint]);
    }
}
