<?php

namespace Redaxo\Core\Addon;

use Override;
use Redaxo\Core\Base\SingletonTrait;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Translation\I18n;

use function sprintf;

/**
 * Represents a null addon.
 *
 * Instances of this class are returned by `Addon::get()` for non-existing addons.
 * Thereby it is safe to call `Addon::get(...)->isAvailable()` and `isInstalled()`.
 * Other methods should not be called on null-addons since they do not return useful values.
 * Some methods like `getPath()` throw exceptions.
 */
final class NullAddon implements AddonInterface
{
    use SingletonTrait;

    #[Override]
    public function getPath(string $file = ''): never
    {
        throw new LogicException(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    #[Override]
    public function getAssetsPath(string $file = ''): never
    {
        throw new LogicException(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    #[Override]
    public function getAssetsUrl(string $file = ''): never
    {
        throw new LogicException(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    #[Override]
    public function getDataPath(string $file = ''): never
    {
        throw new LogicException(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    #[Override]
    public function getCachePath(string $file = ''): never
    {
        throw new LogicException(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    #[Override]
    public function setConfig(string|array $key, mixed $value = null): false
    {
        return false;
    }

    #[Override]
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function hasConfig(?string $key = null): false
    {
        return false;
    }

    #[Override]
    public function removeConfig(string $key): false
    {
        return false;
    }

    #[Override]
    public function setProperty(string $key, mixed $value): void {}

    #[Override]
    public function getProperty(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function hasProperty(string $key): false
    {
        return false;
    }

    #[Override]
    public function removeProperty(string $key): void {}

    #[Override]
    public function isAvailable(): false
    {
        return false;
    }

    #[Override]
    public function isInstalled(): false
    {
        return false;
    }

    #[Override]
    public function getAuthor(?string $default = null): ?string
    {
        return $default;
    }

    #[Override]
    public function getVersion(?string $format = null): string
    {
        return '';
    }

    #[Override]
    public function getSupportPage(?string $default = null): ?string
    {
        return $default;
    }

    #[Override]
    public function includeFile(string $file, array $context = []): null
    {
        return null;
    }

    #[Override]
    public function i18n(string $key, string|int ...$replacements): string
    {
        return I18n::msg($key, ...$replacements);
    }
}
