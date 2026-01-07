<?php

namespace Redaxo\Core\Addon;

use Redaxo\Core\Util\Formatter;

interface AddonInterface
{
    /**
     * Returns the name of the addon.
     *
     * @return non-empty-string Name
     */
    public function getName(): string;

    /**
     * Returns the addon ID.
     *
     * @return non-empty-string|null
     */
    public function getPackageId(): ?string;

    /**
     * Returns the base path.
     *
     * @return non-empty-string
     */
    public function getPath(string $file = ''): string;

    /**
     * Returns the assets path.
     *
     * @return non-empty-string
     */
    public function getAssetsPath(string $file = ''): string;

    /**
     * Returns the assets url.
     *
     * @return non-empty-string
     */
    public function getAssetsUrl(string $file = ''): string;

    /**
     * Returns the data path.
     *
     * @return non-empty-string
     */
    public function getDataPath(string $file = ''): string;

    /**
     * Returns the cache path.
     *
     * @return non-empty-string
     */
    public function getCachePath(string $file = ''): string;

    /**
     * @see Config::set()
     * @param string|array<string, mixed> $key The associated key or an associative array of key/value pairs
     * @param mixed $value The value to save
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    public function setConfig(string|array $key, mixed $value = null): bool;

    /**
     * @see Config::get()
     *
     * @template T as ?string
     * @param T $key The associated key
     * @param mixed $default Default return value if no associated-value can be found
     * @return (T is string ? mixed|null : array<string, mixed>) the value for $key or $default if $key cannot be found in the given $namespace
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed;

    /**
     * @see Config::has()
     * @param string|null $key The associated key
     */
    public function hasConfig(?string $key = null): bool;

    /**
     * @see Config::remove()
     * @param string $key The associated key
     */
    public function removeConfig(string $key): bool;

    /**
     * Sets a property.
     *
     * @param non-empty-string $key Key of the property
     * @param mixed $value New value for the property
     */
    public function setProperty(string $key, mixed $value): void;

    /**
     * Returns a property.
     *
     * @param non-empty-string $key Key of the property
     * @param mixed $default Default value, will be returned if the property isn't set
     */
    public function getProperty(string $key, mixed $default = null): mixed;

    /**
     * Returns if a property is set.
     *
     * @param non-empty-string $key Key of the property
     */
    public function hasProperty(string $key): bool;

    /**
     * Removes a property.
     *
     * @param non-empty-string $key Key of the property
     */
    public function removeProperty(string $key): void;

    /**
     * Returns if the addon is available (activated and installed).
     *
     * @psalm-assert-if-true =Addon $this
     */
    public function isAvailable(): bool;

    /**
     * Returns if the addon is installed.
     *
     * @psalm-assert-if-true =Addon $this
     */
    public function isInstalled(): bool;

    /**
     * Returns if it is a system addon.
     *
     * @psalm-assert-if-true =Addon $this
     */
    public function isSystemPackage(): bool;

    /**
     * Returns the author.
     *
     * @param string|null $default Default value, will be returned if the property isn't set
     */
    public function getAuthor(?string $default = null): ?string;

    /**
     * Returns the version.
     *
     * @param string|null $format See {@link Formatter::version()}
     */
    public function getVersion(?string $format = null): string;

    /**
     * Returns the supportpage.
     *
     * @param string|null $default Default value, will be returned if the property isn't set
     */
    public function getSupportPage(?string $default = null): ?string;

    /**
     * Includes a file in the addon context.
     *
     * @param non-empty-string $file Filename
     * @param array<string, mixed> $context Context values, available as variables in given file
     */
    public function includeFile(string $file, array $context = []): mixed;

    /**
     * Adds the addon prefix to the given key and returns the translation for it.
     *
     * @param string $key Key
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved messag
     *
     * @return non-empty-string Translation for the key
     */
    public function i18n(string $key, string|int ...$replacements): string;
}
