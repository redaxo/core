<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package redaxo\core
 *
 * @internal
 */
final class rex_command_user_list extends rex_console_command
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('List all users or a specific user by login name')
            ->addArgument('user', InputArgument::OPTIONAL, 'Username', null, static function () {
                /** @var list<string> */
                return array_column(rex_sql::factory()->getArray('SELECT login FROM' . rex::getTable('user')), 'login');
            });
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $username = $input->getArgument('user');
        $sql = rex_sql::factory();
        $query = '
            SELECT
                IF(name <> "", name, login) as name,
                `login`,
                `email`,
                IF(`admin`, "Admin", IFNULL((SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ", ") FROM ' . rex::getTable('user_role') . ' r WHERE FIND_IN_SET(r.id, role)), "")) as role,
                `createdate`,
                `lastlogin`
            FROM ' . rex::getTable('user') . '
        ';
        if ($username) {
            $sql->setQuery($query . ' WHERE login = :login', [
                'login' => $username,
            ]);

            if (0 === $sql->getRows()) {
                $io->error(sprintf('The user "%s" does not exist.', $username));
                return Command::FAILURE;
            }
        } else {
            $sql->setQuery($query . ' ORDER BY name');
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Login', 'E-Mail', 'Roles', 'Created', 'Last Login']);

        foreach ($sql as $user) {
            $table->addRow([
                $user->getValue('name'),
                $user->getValue('login'),
                $user->getValue('email'),
                $user->getValue('role'),
                $user->getValue('createdate'),
                $user->getValue('lastlogin'),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
