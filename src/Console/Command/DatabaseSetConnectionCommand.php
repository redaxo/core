<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'db:set-connection',
    description: 'Sets database connection credentials.',
    help: 'Checks by default if a database connection can be established with the new settings.',
)]
class DatabaseSetConnectionCommand extends AbstractCommand implements StandaloneInterface, AvailableInSetupInterface
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'database host')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'database user')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'database password')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'database name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Save credentials even if validation fails.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $configFile = Path::coreData('config.yml');
        $config = File::getConfig($configFile);

        $db = ($config['db'][1] ?? []) + ['host' => '', 'login' => '', 'password' => '', 'name' => ''];

        $changed = false;
        if (null !== $host = $input->getOption('host')) {
            $db['host'] = $host;
            $changed = true;
        }
        if (null !== $login = $input->getOption('login')) {
            $db['login'] = $login;
            $changed = true;
        }
        if (null !== $password = $input->getOption('password')) {
            $db['password'] = $password;
            $changed = true;
        }
        if (null !== $database = $input->getOption('database')) {
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

            if (!$input->getOption('force')) {
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
