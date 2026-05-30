<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'db:set-connection',
    description: 'Sets database connection credentials.',
    help: 'Checks by default if a database connection can be established with the new settings.',
)]
final class DatabaseSetConnectionCommand extends AbstractCommand implements StandaloneInterface, AvailableInSetupInterface
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option('database host')] ?string $host = null,
        #[Option('database user')] ?string $login = null,
        #[Option('database password')] ?string $password = null,
        #[Option('database name')] ?string $database = null,
        #[Option('Save credentials even if validation fails.', shortcut: 'f')] bool $force = false,
    ): int {
        $configFile = Path::coreData('config.yml');
        $config = File::getConfig($configFile);

        $db = ($config['db'][1] ?? []) + ['host' => '', 'login' => '', 'password' => '', 'name' => ''];

        $changed = false;
        if (null !== $host) {
            $db['host'] = $host;
            $changed = true;
        }
        if (null !== $login) {
            $db['login'] = $login;
            $changed = true;
        }
        if (null !== $password) {
            $db['password'] = $password;
            $changed = true;
        }
        if (null !== $database) {
            $db['name'] = $database;
            $changed = true;
        }

        if (!$changed) {
            throw new InvalidArgumentException('No database settings given.');
        }

        $settingsValid = Sql::checkDbConnection(
            $db['host'],
            $db['login'],
            $db['password'],
            $db['name'],
            false,
        );

        if (true !== $settingsValid) {
            $io->error("Can't connect to database:\n" . $settingsValid);

            if (!$force) {
                return Command::FAILURE;
            }
        } else {
            $io->success('Credentials successfully validated.');
        }

        $config['db'][1] = $db;

        if (File::putConfig($configFile, $config)) {
            $io->success('Database settings successfully saved');
            return Command::SUCCESS;
        }
        $io->error("Database settings could't be saved.");
        return Command::FAILURE;
    }
}
