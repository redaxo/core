<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\BackendPasswordPolicy;
use Symfony\Component\Console\Attribute\AsCommand;
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
#[AsCommand(name: 'user:create', description: 'Create a new user')]
class UserCreateCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('login', InputArgument::REQUIRED, 'Login')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant admin permissions')
            ->addOption('password-change-required', null, InputOption::VALUE_NONE, 'Require password change after login')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $login = $input->getArgument('login');

        $user = Sql::factory();
        $user
            ->setTable(Core::getTable('user'))
            ->setWhere(['login' => $login])
            ->select();

        if ($user->getRows()) {
            throw new InvalidArgumentException(sprintf('User "%s" already exists.', $login));
        }

        $passwordPolicy = BackendPasswordPolicy::factory();

        $password = $input->getArgument('password');
        if ($password && true !== $msg = $passwordPolicy->check($password)) {
            throw new InvalidArgumentException($msg);
        }

        if (!$password) {
            $description = $passwordPolicy->getDescription();
            $description = $description ? ' (' . $description . ')' : '';

            $password = $io->askHidden('Password' . $description, static function ($password) use ($passwordPolicy) {
                if (true !== $msg = $passwordPolicy->check($password)) {
                    throw new InvalidArgumentException($msg);
                }

                return $password;
            });
        }

        if (!$password) {
            throw new InvalidArgumentException('Missing password.');
        }

        $name = $input->getOption('name');
        if (!$name) {
            $name = $login;
        }

        $passwordHash = BackendLogin::passwordHash($password);

        $user = Sql::factory();
        // $user->setDebug();
        $user->setTable(Core::getTablePrefix() . 'user');
        $user->setValue('name', $name);
        $user->setValue('login', $login);
        $user->setValue('password', $passwordHash);
        $user->setValue('admin', $input->getOption('admin') ? 1 : 0);
        $user->setValue('login_tries', 0);
        $user->addGlobalCreateFields('console');
        $user->addGlobalUpdateFields('console');
        $user->setDateTimeValue('password_changed', time());
        $user->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords(null, $passwordHash));
        $user->setValue('password_change_required', (int) $input->getOption('password-change-required'));
        $user->setValue('status', '1');
        $user->insert();

        $io->success(sprintf('User "%s" successfully created.', $login));

        return Command::SUCCESS;
    }
}
