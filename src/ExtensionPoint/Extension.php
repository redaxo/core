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
                    /** @var T|null $subject */
                    $subject = call_user_func($extension, $extensionPoint);
                    // Update subject only if the EP is not readonly and the extension has returned something
                    if ($extensionPoint->readonly) {
                        continue;
                    }
                    if (null === $subject) {
                        continue;
                    }
                    $extensionPoint->subject = $subject;
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
     * Registers all extensions for methods carrying the `#[AsExtension]` attribute,
     * discovered via {@see ClassDiscovery}.
     *
     * Static methods are bound to their class; non-static methods are allowed on
     * the project class and on addon classes (where the instance is available).
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
                throw new LogicException(sprintf(
                    'Non-static #[AsExtension] is only allowed on the project class or on addon classes. Method "%s::%s()" is neither static nor defined on a parent of %s or on a registered addon class.',
                    $entry['class'],
                    $entry['method'],
                    $project::class,
                ));
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
}
