<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'user:delete', description: 'Deletes an user by the specified login name.')]
final class UserDeleteCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'Username', null, static function () {
                /** @var list<string> */
                return array_column(Sql::factory()->getArray('SELECT login FROM ' . Core::getTable('user')), 'login');
            })
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $username = $input->getArgument('user');

        $user = User::forLogin($username);

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

    private function deleteUser(User $user): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTable('user'));
        $sql->setWhere(['id' => $user->id])->delete();

        User::clearInstance($user->id);

        Extension::registerPoint(new ExtensionPoint('USER_DELETED', '', [
            'id' => $user->id,
            'user' => $user,
        ], true));
    }
}
