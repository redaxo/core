<?php

namespace Redaxo\Core\ExtensionPoint;

use Redaxo\Core\AbstractProject;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Util\Timer;

use function call_user_func;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

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
     * Registers all extensions for methods carrying the `#[AsExtension]` attribute,
     * discovered via {@see ClassDiscovery}.
     *
     * Static methods are bound to their class; non-static methods are allowed on
     * the project class and on addon classes (where the instance is available).
     *
     * @internal
     */
    public static function registerByAttribute(AbstractProject $project): void
    {
        /** @var array<class-string<Addon>, Addon>|null $addonByClass */
        $addonByClass = null;

        foreach (ClassDiscovery::getInstance()->discoverByMethodAttribute(AsExtension::class) as $entry) {
            if ($entry['isStatic']) {
                $callable = [$entry['class'], $entry['method']];
            } elseif ($project instanceof $entry['class']) {
                $callable = [$project, $entry['method']];
            } elseif (is_subclass_of($entry['class'], Addon::class)) {
                if (null === $addonByClass) {
                    $addonByClass = [];
                    foreach (Addon::getAvailableAddons() as $addon) {
                        $addonByClass[$addon::class] = $addon;
                    }
                }
                if (!isset($addonByClass[$entry['class']])) {
                    throw new LogicException(sprintf(
                        'Non-static #[AsExtension] on addon class "%s::%s()" cannot be registered: addon is not available.',
                        $entry['class'],
                        $entry['method'],
                    ));
                }
                $callable = [$addonByClass[$entry['class']], $entry['method']];
            } else {
                throw new LogicException(sprintf(
                    'Non-static #[AsExtension] is only allowed on the project class or on addon classes. Method "%s::%s()" is neither static nor defined on a parent of %s or on a registered addon class.',
                    $entry['class'],
                    $entry['method'],
                    $project::class,
                ));
            }

            self::register(
                $entry['attribute']->extensionPoint,
                $callable,
                $entry['attribute']->level,
            );
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
