<?php

namespace Redaxo\Core\ExtensionPoint;

use Redaxo\Core\AbstractProject;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Util\Timer;

use function call_user_func;
use function constant;
use function defined;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Klasse die Einsprungpunkte zur Erweiterung der Kernfunktionalitaet bietet.
 */
class Extension
{
    use FactoryTrait;

    /**
     * Array of registered extensions.
     *
     * @var array<string, array<string, list<callable>>>
     */
    private static array $extensions = [];

    /**
     * Object instances backing non-static `#[AsExtension]` methods, in registration order.
     * Populated via {@see self::registerInstance()} and read lazily on dispatch: an attributed method
     * runs once for every registered instance that is an `instanceof` the method's declaring class.
     *
     * @var list<object>
     */
    private static array $instances = [];

    final private function __construct() {}

    /**
     * Dispatches an extension point, running all registered extensions.
     *
     * @template T
     * @param ExtensionPoint<T> $extensionPoint Extension point
     * @return T Subject, maybe adjusted by the extensions
     *
     * @psalm-taint-specialize
     */
    public static function dispatch(ExtensionPoint $extensionPoint): mixed
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::dispatch($extensionPoint);
        }

        $name = $extensionPoint->name;

        Timer::measure('EP: ' . $name, static function () use ($extensionPoint, $name) {
            foreach (ExtensionLevel::cases() as $level) {
                if (!isset(self::$extensions[$name][$level->name]) || !is_array(self::$extensions[$name][$level->name])) {
                    continue;
                }

                foreach (self::$extensions[$name][$level->name] as $extension) {
                    self::runExtension($extensionPoint, $extension);
                }
            }
        });

        return $extensionPoint->subject;
    }

    /**
     * Registers an extension for an extension point.
     *
     * @template T as ExtensionPoint
     * @param string|list<string> $extensionPoint Name(s) of extension point(s)
     * @param callable(T):mixed $extension Callback extension
     * @param ExtensionLevel $level Run level (`ExtensionLevel::Early`, `ExtensionLevel::Normal` or `ExtensionLevel::Late`)
     */
    public static function register(string|array $extensionPoint, callable $extension, ExtensionLevel $level = ExtensionLevel::Normal): void
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::register($extensionPoint, $extension, $level);
            return;
        }

        foreach ((array) $extensionPoint as $ep) {
            self::$extensions[$ep][$level->name][] = $extension;
        }
    }

    /**
     * Registers an object instance to back non-static `#[AsExtension]` methods.
     *
     * Instances are matched to attributed methods by `instanceof` on dispatch, so an instance also serves
     * attributed methods declared on its parent classes. Multiple instances (of the same or related classes)
     * may be registered; each attributed method then runs once per matching instance, in registration order.
     *
     * The instances are read lazily on dispatch, so this may be called at any point before the extension
     * point is actually dispatched (e.g. only on the page where the extension is relevant).
     *
     * @see self::registerByAttribute()
     */
    public static function registerInstance(object $instance): void
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::registerInstance($instance);
            return;
        }

        self::$instances[] = $instance;
    }

    /**
     * Registers all extensions for methods carrying the `#[AsExtension]` attribute,
     * discovered via {@see ClassDiscovery}.
     *
     * Static methods are bound to their class. Non-static methods on the project class or on addon classes
     * are bound to those (already available) instances; all other non-static methods run on dispatch for every
     * matching instance registered via {@see self::registerInstance()}.
     *
     * @internal
     */
    final public static function registerByAttribute(AbstractProject $project): void
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
                    foreach (Addon::getActivatedAddons() as $addon) {
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
                // Generic instance method: the backing objects are resolved lazily on dispatch from the
                // instances registered via self::registerInstance(). Resolving lazily (rather than here at boot)
                // keeps registration timing flexible — instances only need to exist when the EP fires. The method
                // runs once per matching instance, chaining the subject between them exactly like separately
                // registered extensions (see self::dispatch()). If no instance matches, it is simply a no-op.
                $class = $entry['class'];
                $method = $entry['method'];
                $callable = static function (ExtensionPoint $ep) use ($class, $method): mixed {
                    foreach (self::$instances as $instance) {
                        if (!$instance instanceof $class) {
                            continue;
                        }

                        /** @var callable(ExtensionPoint<mixed>):mixed $callback */
                        $callback = [$instance, $method];
                        self::runExtension($ep, $callback);
                    }

                    return $ep->subject;
                };
            }

            $extensionPoint = $entry['attribute']->extensionPoint
                ?? self::resolveExtensionPointNames($entry['firstParameterTypes'], $entry['class'], $entry['method']);

            self::register(
                $extensionPoint,
                $callable,
                $entry['attribute']->level,
            );
        }
    }

    /**
     * Derives the extension point name(s) from the type of an extension method's first parameter,
     * used when `#[AsExtension]` is given without an explicit name. A union type registers the
     * extension for every listed extension point.
     *
     * @param list<class-string> $firstParameterTypes
     * @return non-empty-list<string>
     */
    private static function resolveExtensionPointNames(array $firstParameterTypes, string $class, string $method): array
    {
        $names = [];

        foreach ($firstParameterTypes as $type) {
            if (!is_subclass_of($type, ExtensionPoint::class)) {
                continue;
            }

            // The name is the EP class' `NAME` constant if defined, otherwise its FQCN — matching the
            // name an instance of that class reports when dispatched.
            if (defined($type . '::NAME')) {
                /** @var mixed $name */
                $name = constant($type . '::NAME');
                $names[] = is_string($name) ? $name : $type;
            } else {
                $names[] = $type;
            }
        }

        if ([] === $names) {
            throw new LogicException(sprintf(
                '#[AsExtension] without an explicit extension point name on "%s::%s()" requires the first parameter to be typed as one or more %s subclasses.',
                $class,
                $method,
                ExtensionPoint::class,
            ));
        }

        return $names;
    }

    /**
     * Checks whether any extension is registered for the given extension point.
     *
     * @param string $extensionPoint Name of extension point
     */
    public static function hasExtensions(string $extensionPoint): bool
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::hasExtensions($extensionPoint);
        }
        return !empty(self::$extensions[$extensionPoint]);
    }

    /**
     * Runs a single extension callback against the extension point and applies its return value to the subject,
     * honoring the "readonly" flag and the convention that a `null` return means "no change". Shared by
     * {@see self::dispatch()} and the instance-method wrapper built in {@see self::registerByAttribute()}.
     *
     * @param ExtensionPoint<mixed> $extensionPoint
     */
    private static function runExtension(ExtensionPoint $extensionPoint, callable $extension): void
    {
        /** @var mixed $result */
        $result = call_user_func($extension, $extensionPoint);

        if ($extensionPoint->readonly || null === $result) {
            return;
        }

        $extensionPoint->subject = $result;
    }
}
