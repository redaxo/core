<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\BackendPasswordPolicy;
use Redaxo\Core\Security\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @internal
 */
class UserSetPasswordCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Sets a new password for a user')
            ->addArgument('user', InputArgument::REQUIRED, 'Username', null, static function () {
                /** @var list<string> */
                return array_column(Sql::factory()->getArray('SELECT login FROM ' . Core::getTable('user')), 'login');
            })
            ->addArgument('password', InputArgument::OPTIONAL, 'Password')
            ->addOption('password-change-required', null, InputOption::VALUE_NONE, 'Require password change after login')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $username = $input->getArgument('user');

        $user = Sql::factory();
        $user
            ->setTable(Core::getTable('user'))
            ->setWhere(['login' => $username])
            ->select();

        if (!$user->getRows()) {
            throw new InvalidArgumentException(sprintf('User "%s" does not exist.', $username));
        }

        $user = User::fromSql($user);
        $id = $user->getId();

        $passwordPolicy = BackendPasswordPolicy::factory();

        $password = $input->getArgument('password');

        if ($password && true !== $msg = $passwordPolicy->check($password, $id)) {
            throw new InvalidArgumentException($msg);
        }

        if (!$password) {
            $description = $passwordPolicy->getDescription();
            $description = $description ? ' (' . $description . ')' : '';

            $password = $io->askHidden('Password' . $description, static function ($password) use ($id, $passwordPolicy) {
                if (true !== $msg = $passwordPolicy->check($password, $id)) {
                    throw new InvalidArgumentException($msg);
                }

                return $password;
            });
        }

        if (!$password) {
            throw new InvalidArgumentException('Missing password.');
        }

        $passwordHash = BackendLogin::passwordHash($password);

        Sql::factory()
            ->setTable(Core::getTable('user'))
            ->setWhere(['id' => $id])
            ->setValue('password', $passwordHash)
            ->setValue('login_tries', 0)
            ->addGlobalUpdateFields('console')
            ->setDateTimeValue('password_changed', time())
            ->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash))
            ->setValue('password_change_required', (int) $input->getOption('password-change-required'))
            ->update();

        Extension::registerPoint(new ExtensionPoint('PASSWORD_UPDATED', '', [
            'user_id' => $id,
            'user' => $user,
            'password' => $password,
        ], true));

        $io->success(sprintf('Saved new password for user "%s".', $username));

        return Command::SUCCESS;
    }
}
