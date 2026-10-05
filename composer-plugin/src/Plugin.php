<?php

namespace Redaxo\ComposerPlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Override;

use function dirname;
use function is_array;
use function realpath;
use function sprintf;
use function strrchr;
use function substr;

/**
 * @internal
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const string CORE = 'redaxo/core';

    /** Whether the core or an addon has been installed, updated or removed during the current composer run */
    private bool $redaxoChanged = false;

    /** Whether the core has been installed from scratch, i.e. in a new project or a fresh checkout */
    private bool $coreInstalled = false;

    /** @var list<string> */
    private array $removedAddons = [];

    #[Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        self::configureRuntime($composer);
    }

    #[Override]
    public function deactivate(Composer $composer, IOInterface $io): void {}

    #[Override]
    public function uninstall(Composer $composer, IOInterface $io): void {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageChange',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageChange',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'onPackageChange',
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'onPostCreateProject',
        ];
    }

    public function onPackageChange(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        $package = match (true) {
            $operation instanceof InstallOperation, $operation instanceof UninstallOperation => $operation->getPackage(),
            $operation instanceof UpdateOperation => $operation->getTargetPackage(),
            default => null,
        };

        if (!$package || !self::isRedaxoPackage($package)) {
            return;
        }

        $this->redaxoChanged = true;

        if ($operation instanceof InstallOperation && self::CORE === $package->getName()) {
            $this->coreInstalled = true;
        }

        if ($operation instanceof UninstallOperation && 'redaxo-addon' === $package->getType()) {
            $this->removedAddons[] = substr((string) strrchr($package->getName(), '/'), 1);
        }
    }

    public function onPostInstallOrUpdate(Event $event): void
    {
        $io = $event->getIO();

        foreach ($this->removedAddons as $addon) {
            $io->write([
                '',
                sprintf('<warning>The addon "%s" has been removed, but its database tables and data, if any, are kept: its uninstall routine can only run while the addon is installed.</warning>', $addon),
                sprintf('<warning>To remove them as well, reinstall the addon, run `php bin/console addon:uninstall %s` and remove it again.</warning>', $addon),
            ]);
        }

        if ($this->coreInstalled) {
            $io->write([
                '',
                '<info>REDAXO has been installed. Run `php bin/console setup` to set up a new installation, or `php bin/console migrate` if its database already exists.</info>',
            ]);
        } elseif ($this->redaxoChanged) {
            $io->write([
                '',
                '<info>REDAXO or its addons have changed. Run `php bin/console migrate` to bring the database in line.</info>',
            ]);
        }

        $this->redaxoChanged = false;
        $this->coreInstalled = false;
        $this->removedAddons = [];
    }

    public function onPostCreateProject(Event $event): void
    {
        // Only for the official skeleton: other skeletons based on REDAXO bring their own initialization.
        if ('redaxo/project' !== $event->getComposer()->getPackage()->getName()) {
            return;
        }

        new ProjectInitializer((string) realpath(dirname(Factory::getComposerFile())))->initialize();
    }

    /**
     * Makes `Redaxo\Core\Runtime` the default runtime of symfony/runtime, which reads `extra.runtime` of the root
     * package only when it generates `vendor/autoload_runtime.php` (on `post-autoload-dump`).
     */
    public static function configureRuntime(Composer $composer): void
    {
        $package = $composer->getPackage();
        $extra = $package->getExtra();
        $runtime = $extra['runtime'] ?? [];

        // `false` disables the runtime generation, an existing class is the project's own choice.
        if (!is_array($runtime) || isset($runtime['class'])) {
            return;
        }

        $runtime['class'] = 'Redaxo\Core\Runtime';
        $extra['runtime'] = $runtime;
        $package->setExtra($extra);
    }

    private static function isRedaxoPackage(PackageInterface $package): bool
    {
        return self::CORE === $package->getName() || 'redaxo-addon' === $package->getType();
    }
}
