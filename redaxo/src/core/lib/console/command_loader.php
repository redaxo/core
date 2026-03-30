<?php

use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * @package redaxo\core
 *
 * @internal
 */
class rex_console_command_loader implements CommandLoaderInterface
{
    /** @var array<string, array{class: class-string<rex_console_command>, package?: rex_package}> */
    private $commands = [];

    public function __construct()
    {
        $commands = [
            'cache:clear' => rex_command_cache_clear::class,
            'config:get' => rex_command_config_get::class,
            'config:set' => rex_command_config_set::class,
            'db:connection-options' => rex_command_db_connection_options::class,
            'db:set-connection' => rex_command_db_set_connection::class,
            'setup:check' => rex_command_setup_check::class,
            'setup:run' => rex_command_setup_run::class,
        ];

        if (!rex::isSetup()) {
            $commands = array_merge($commands, [
                'assets:sync' => rex_command_assets_sync::class,
                'db:dump-schema' => rex_command_db_dump_schema::class,
                'package:activate' => rex_command_package_activate::class,
                'package:deactivate' => rex_command_package_deactivate::class,
                'package:delete' => rex_command_package_delete::class,
                'package:list' => rex_command_package_list::class,
                'package:install' => rex_command_package_install::class,
                'package:run-update-script' => rex_command_package_run_update_script::class,
                'package:uninstall' => rex_command_package_uninstall::class,
                'system:report' => rex_command_system_report::class,
                'user:create' => rex_command_user_create::class,
                'user:delete' => rex_command_user_delete::class,
                'user:list' => rex_command_user_list::class,
                'user:set-password' => rex_command_user_set_password::class,
            ]);
        }

        foreach ($commands as $command => $class) {
            $this->commands[$command] = ['class' => $class];
        }

        foreach (rex_package::getAvailablePackages() as $package) {
            /** @var array<string, class-string<rex_console_command>> $commands */
            $commands = $package->getProperty('console_commands');

            if (!$commands) {
                continue;
            }

            if (!is_array($commands)) {
                throw new rex_exception('Expecting "console_commands" property to be an array, got "' . gettype($commands) . '" from package.yml of "' . $package->getName() . '"');
            }

            foreach ($commands as $command => $class) {
                $this->commands[$command] = [
                    'package' => $package,
                    'class' => $class,
                ];
            }
        }
    }

    public function get(string $name): rex_console_command
    {
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $class = $this->commands[$name]['class'];

        $command = new $class();
        $command->setName($name);

        if (isset($this->commands[$name]['package'])) {
            $command->setPackage($this->commands[$name]['package']);
        }

        return $command;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /** @return list<string> */
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
