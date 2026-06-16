<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Exception\RuntimeException;
use ReflectionClass;

use function sprintf;
use function substr;

/**
 * Resolves the view file for a class, honouring registered overrides.
 *
 * By default a class's view is the co-located `ClassName.view.php`. A project or addon can replace
 * the view of any class — without touching that class — by registering an override for its FQCN
 * (typically in its boot code). The override is identity-based, not path-based: it does not depend
 * on where the original view lives. Last registration wins, so addons can override core and the
 * project can override both.
 */
final class ViewResolver
{
    /** @var array<class-string, string> */
    private static array $overrides = [];

    /** @var array<class-string, string> */
    private static array $resolved = [];

    /**
     * Registers a view file to be used for the given class instead of its co-located default.
     *
     * @param class-string $class
     */
    public static function override(string $class, string $viewFile): void
    {
        self::$overrides[$class] = $viewFile;
        unset(self::$resolved[$class]);
    }

    /** @param class-string $class */
    public static function resolve(string $class): string
    {
        return self::$resolved[$class] ??= self::$overrides[$class] ?? self::locate($class);
    }

    /** @param class-string $class */
    private static function locate(string $class): string
    {
        $classFile = new ReflectionClass($class)->getFileName();

        if (false === $classFile) {
            throw new RuntimeException(sprintf('Cannot resolve a view for "%s": the class has no source file.', $class));
        }

        return substr($classFile, 0, -4) . '.view.php';
    }

    /** @internal Resets the registry; intended for test isolation. */
    public static function reset(): void
    {
        self::$overrides = [];
        self::$resolved = [];
    }
}
