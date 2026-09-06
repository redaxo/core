<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Environment;
use Redaxo\Core\Security\BackendLogin;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'user:create', description: 'Create a new user')]
final class UserCreateCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Login')] string $login,
        #[Argument('Password')] ?string $password = null,
        #[Option('Name')] ?string $name = null,
        #[Option('Grant admin permissions')] bool $admin = false,
        #[Option('Require password change after login')] bool $passwordChangeRequired = false,
    ): int {
        $user = Sql::factory();
        $user
            ->setTable(Core::getTable('user'))
            ->setWhere(['login' => $login])
            ->select();

        if ($user->getRows()) {
            throw new InvalidArgumentException(sprintf('User "%s" already exists.', $login));
        }

        $passwordPolicy = BackendLogin::getPasswordPolicy();

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

        if (!$name) {
            $name = $login;
        }

        $passwordHash = BackendLogin::passwordHash($password);

        $user = Sql::factory();
        $user->setTable(Core::getTablePrefix() . 'user');
        $user->setValue('name', $name);
        $user->setValue('login', $login);
        $user->setValue('password', $passwordHash);
        $user->setValue('admin', $admin ? 1 : 0);
        $user->setValue('login_tries', 0);
        $user->addGlobalCreateFields(Environment::Console->value);
        $user->addGlobalUpdateFields(Environment::Console->value);
        $user->setDateTimeValue('password_changed', time());
        $user->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords(null, $passwordHash));
        $user->setValue('password_change_required', (int) $passwordChangeRequired);
        $user->setValue('status', '1');
        $user->insert();

        $io->success(sprintf('User "%s" successfully created.', $login));

        return Command::SUCCESS;
    }
}
