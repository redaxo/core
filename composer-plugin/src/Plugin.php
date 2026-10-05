<?php

namespace Redaxo\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Override;

use function dirname;
use function is_array;
use function realpath;

/**
 * @internal
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
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
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'onPostCreateProject',
        ];
    }

    public function onPostCreateProject(Event $event): void
    {
        // Only for the official skeleton: other skeletons based on REDAXO bring their own initialization.
        if ('redaxo/project' !== $event->getComposer()->getPackage()->getName()) {
            return;
        }

        new ProjectInitializer((string) realpath(dirname(Factory::getComposerFile())))->initialize();

        $event->getIO()->write('Initialized composer.json, .env and README.md for the new project.');
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
}
