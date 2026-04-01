<?php

namespace Redaxo\Core\Console;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Console\Command\AbstractCommand;
use Redaxo\Core\Console\Command\AddonActivateCommand;
use Redaxo\Core\Console\Command\AddonDeactivateCommand;
use Redaxo\Core\Console\Command\AddonInstallCommand;
use Redaxo\Core\Console\Command\AddonListCommand;
use Redaxo\Core\Console\Command\AddonRunUpdateScriptCommand;
use Redaxo\Core\Console\Command\AddonUninstallCommand;
use Redaxo\Core\Console\Command\AssetsCompileStylesCommand;
use Redaxo\Core\Console\Command\AssetsSyncCommand;
use Redaxo\Core\Console\Command\CacheClearCommand;
use Redaxo\Core\Console\Command\ConfigGetCommand;
use Redaxo\Core\Console\Command\ConfigSetCommand;
use Redaxo\Core\Console\Command\CronjobRunCommand;
use Redaxo\Core\Console\Command\DatabaseConnectionOptionsCommand;
use Redaxo\Core\Console\Command\DatabaseDumpSchemaCommand;
use Redaxo\Core\Console\Command\DatabaseSetConnectionCommand;
use Redaxo\Core\Console\Command\SetupCheckCommand;
use Redaxo\Core\Console\Command\SetupRunCommand;
use Redaxo\Core\Console\Command\SystemReportCommand;
use Redaxo\Core\Console\Command\UserCreateCommand;
use Redaxo\Core\Console\Command\UserDeleteCommand;
use Redaxo\Core\Console\Command\UserListCommand;
use Redaxo\Core\Console\Command\UserSetPasswordCommand;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

use function gettype;
use function is_array;
use function sprintf;

/**
 * @internal
 */
final class CommandLoader implements CommandLoaderInterface
{
    /** @var array<string, array{class: class-string<AbstractCommand>, addon?: Addon}> */
    private array $commands = [];

    public function __construct()
    {
        $commands = [
            'cache:clear' => CacheClearCommand::class,
            'config:get' => ConfigGetCommand::class,
            'config:set' => ConfigSetCommand::class,
            'db:connection-options' => DatabaseConnectionOptionsCommand::class,
            'db:set-connection' => DatabaseSetConnectionCommand::class,
            'setup:check' => SetupCheckCommand::class,
            'setup:run' => SetupRunCommand::class,
        ];

        if (!Core::isSetup()) {
            $commands = array_merge($commands, [
                'assets:sync' => AssetsSyncCommand::class,
                'assets:compile-styles' => AssetsCompileStylesCommand::class,
                'cronjob:run' => CronjobRunCommand::class,
                'db:dump-schema' => DatabaseDumpSchemaCommand::class,
                'addon:activate' => AddonActivateCommand::class,
                'addon:deactivate' => AddonDeactivateCommand::class,
                'addon:list' => AddonListCommand::class,
                'addon:install' => AddonInstallCommand::class,
                'addon:run-update-script' => AddonRunUpdateScriptCommand::class,
                'addon:uninstall' => AddonUninstallCommand::class,
                'system:report' => SystemReportCommand::class,
                'user:create' => UserCreateCommand::class,
                'user:delete' => UserDeleteCommand::class,
                'user:list' => UserListCommand::class,
                'user:set-password' => UserSetPasswordCommand::class,
            ]);
        }

        foreach ($commands as $command => $class) {
            $this->commands[$command] = ['class' => $class];
        }

        foreach (Addon::getAvailableAddons() as $addon) {
            /** @var array<string, class-string<AbstractCommand>> $commands */
            $commands = $addon->getProperty('console_commands');

            if (!$commands) {
                continue;
            }

            if (!is_array($commands)) {
                throw new RuntimeException('Expecting "console_commands" property to be an array, got "' . gettype($commands) . '" from package.yml of "' . $addon->getName() . '"');
            }

            foreach ($commands as $command => $class) {
                $this->commands[$command] = [
                    'addon' => $addon,
                    'class' => $class,
                ];
            }
        }
    }

    #[Override]
    public function get(string $name): AbstractCommand
    {
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $class = $this->commands[$name]['class'];

        $command = new $class();
        $command->setName($name);

        if (isset($this->commands[$name]['addon'])) {
            $command->setAddon($this->commands[$name]['addon']);
        }

        return $command;
    }

    #[Override]
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /** @return list<string> */
    #[Override]
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
