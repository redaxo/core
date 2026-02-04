<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package redaxo\core
 *
 * @internal
 */
final class rex_command_user_delete extends rex_console_command
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Deletes an user by the specified login name.')
            ->addArgument('user', InputArgument::REQUIRED, 'Username', null, static function () {
                /** @var list<string> */
                return array_column(rex_sql::factory()->getArray('SELECT login FROM ' . rex::getTable('user')), 'login');
            })
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $username = $input->getArgument('user');

        $user = rex_user::forLogin($username);

        if (!$user) {
            $io->error(sprintf('The user "%s" does not exist.', $username));
            return Command::FAILURE;
        }

        $askConfirmationQuestion = $io->confirm(sprintf('Are you sure you would like to delete user "%s"?', $username), false);
        if (!$askConfirmationQuestion) {
            $io->info(sprintf('Aborted. User "%s" was not deleted.', $username));
            return Command::FAILURE;
        }

        $this->deleteUser($user);
        $io->success(sprintf('User "%s" has been successfully deleted.', $username));

        return Command::SUCCESS;
    }

    private function deleteUser(rex_user $user): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('user'));
        $sql->setWhere(['id' => $user->getId()])->delete();

        rex_user::clearInstance($user->getId());

        rex_extension::registerPoint(new rex_extension_point('USER_DELETED', '', [
            'id' => $user->getId(),
            'user' => $user,
        ], true));
    }
}
