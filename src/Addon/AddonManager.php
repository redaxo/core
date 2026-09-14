<?php

namespace Redaxo\Core\Addon;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function array_values;
use function class_exists;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_subclass_of;
use function json_decode;
use function sort;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class AddonManager
{
    use FactoryTrait;

    /**
     * Config key listing the addons whose `install()` has already run on this instance.
     *
     * This is instance state, written by `migrate` only — never a switch. It tells an addon that is new to
     * this instance (whose schema `install()` just created in the shape the current code describes) apart
     * from one that has been here before (whose pending migrations still have to run).
     */
    private const string CONFIG_INSTALLED_PACKAGES = 'installed_packages';

    protected string $message = '';

    final protected function __construct(
        protected readonly Addon $addon,
    ) {}

    /** Creates the manager for the addon. */
    public static function factory(Addon $addon): static
    {
        $class = static::getFactoryClass();
        return new $class($addon);
    }

    /** Returns the message. */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Brings the addon's schema and data in line with its code by running its `install()` hook.
     *
     * Idempotent by contract, because `migrate` runs it on every deploy.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function install(): bool
    {
        try {
            I18n::addDirectory($this->addon->getPath('lang'));

            $firstInstall = !self::hasBeenInstalled($this->addon->name);

            // run install hook (can only abort by throwing a UserMessageException)
            $this->addon->install();

            foreach ($this->addon->defaultConfig as $key => $value) {
                if (!$this->addon->hasConfig($key)) {
                    $this->addon->setConfig($key, $value);
                }
            }

            // copy assets
            $assets = $this->addon->getPath('assets');
            if (is_dir($assets) && !Dir::copy($assets, $this->addon->getAssetsPath())) {
                throw new UserMessageException($this->i18n('install_cant_copy_files'));
            }

            if ($firstInstall) {
                // install() created the current schema, so the migrations leading there must not run again
                Migrator::baseline($this->addon->name);
                self::markInstalled($this->addon->name);
            }

            $this->message = $this->i18n('installed', $this->addon->name);
            $successMessage = (string) $this->addon->getProperty('successmsg', '');
            if ($successMessage) {
                $this->message .= ' ' . $successMessage;
            }

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->message = $this->i18n('no_install', $this->addon->name) . '<br />' . $this->message;

        return false;
    }

    /**
     * Removes the addon's data by running its `uninstall()` hook.
     *
     * Has to run while the addon's code is still there, so removing an addon is uninstall first, then
     * `composer remove`. The other way round its tables stay behind for good.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function uninstall(): bool
    {
        try {
            I18n::addDirectory($this->addon->getPath('lang'));

            // run uninstall hook (can only abort by throwing a UserMessageException)
            $this->addon->uninstall();

            // delete assets
            $assets = $this->addon->getAssetsPath();
            if (is_dir($assets) && !Dir::delete($assets)) {
                throw new UserMessageException($this->i18n('install_cant_delete_files'));
            }

            $this->addon->clearCache();
            Config::removeNamespace($this->addon->name);

            // the addon dropped its own tables, so its migration history is gone too
            Migrator::forget($this->addon->name);
            self::forgetInstalled($this->addon->name);

            $this->message = $this->i18n('uninstalled', $this->addon->name);

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->message = $this->i18n('no_uninstall', $this->addon->name) . '<br />' . $this->message;

        return false;
    }

    /** Translates the given key, prefixed with `addon_`. */
    protected function i18n(string $key, string|int ...$replacements): string
    {
        return I18n::msg('addon_' . $key, ...$replacements);
    }

    /**
     * Returns the addons whose `install()` has already run on this instance.
     *
     * @return list<non-empty-string>
     */
    private static function getInstalledPackages(): array
    {
        /** @var list<non-empty-string> */
        return Type::array(Core::getConfig(self::CONFIG_INSTALLED_PACKAGES, []));
    }

    /** Returns whether the addon's `install()` has already run on this instance. */
    private static function hasBeenInstalled(string $addon): bool
    {
        return in_array($addon, self::getInstalledPackages(), true);
    }

    /**
     * Returns the addons that have data on this instance but are no longer installed via composer.
     *
     * Their own uninstall hook can no longer run because their code is gone, so their tables stay behind.
     * Nothing is cleaned up automatically: a `composer install --no-dev` makes a dev-only addon look exactly
     * like this, and dropping its tables because of that would be fatal.
     *
     * @return list<non-empty-string>
     */
    public static function getOrphans(): array
    {
        return array_values(array_filter(
            self::getInstalledPackages(),
            static fn (string $addon): bool => !Addon::exists($addon),
        ));
    }

    /** @param non-empty-string $addon */
    private static function markInstalled(string $addon): void
    {
        $packages = self::getInstalledPackages();

        if (in_array($addon, $packages, true)) {
            return;
        }

        $packages[] = $addon;
        sort($packages);

        Core::setConfig(self::CONFIG_INSTALLED_PACKAGES, $packages);
    }

    private static function forgetInstalled(string $addon): void
    {
        Core::setConfig(self::CONFIG_INSTALLED_PACKAGES, array_values(array_filter(
            self::getInstalledPackages(),
            static fn (string $package): bool => $package !== $addon,
        )));
    }

    /**
     * Returns the addon-class mapping for all registered `redaxo-addon` packages, read from `vendor/composer/installed.json`.
     *
     * @internal
     *
     * @return array<non-empty-string, class-string<Addon>>
     */
    public static function getAddonClasses(): array
    {
        $classes = [];

        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $_loader) {
            $jsonPath = $vendorDir . '/composer/installed.json';
            if (!is_file($jsonPath)) {
                continue;
            }

            $json = File::get($jsonPath);
            if (!$json) {
                continue;
            }

            /** @var array{packages?: list<array<string, mixed>>} $data */
            $data = Type::array(json_decode($json, true, flags: JSON_THROW_ON_ERROR));

            foreach ($data['packages'] ?? [] as $package) {
                if (($package['type'] ?? null) !== 'redaxo-addon') {
                    continue;
                }

                $name = Path::basename(Type::string($package['name'] ?? ''));
                /** @var array<string, mixed> $extra */
                $extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
                /** @var array<string, mixed> $redaxoExtra */
                $redaxoExtra = is_array($extra['redaxo'] ?? null) ? $extra['redaxo'] : [];
                $class = $redaxoExtra['addon-class'] ?? null;

                if (!is_string($class) || '' === $class || '' === $name) {
                    continue;
                }
                if (!class_exists($class)) {
                    throw new RuntimeException(sprintf('Addon class "%s" of addon "%s" does not exist.', $class, $name));
                }
                if (!is_subclass_of($class, Addon::class)) {
                    throw new RuntimeException(sprintf('Addon class "%s" of addon "%s" must extend %s.', $class, $name, Addon::class));
                }

                /** @var class-string<Addon> $class */
                $classes[$name] = $class;
            }
        }

        return $classes;
    }

    /** @return array<non-empty-string, non-empty-string> */
    public static function getComposerPackages(): array
    {
        $packages = [];
        foreach (InstalledVersions::getInstalledPackagesByType('redaxo-addon') as $package) {
            $addon = Path::basename($package);

            if (isset($packages[$addon])) {
                throw new RuntimeException(sprintf('The composer packages "%s" and "%s" have the same addon name "%s".', $packages[$addon], $package, $addon));
            }

            $packages[$addon] = $package;
        }

        /** @var array<non-empty-string, non-empty-string> */
        return $packages;
    }
}
