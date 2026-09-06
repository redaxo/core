<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Environment;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;
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
#[AsCommand(name: 'user:set-password', description: 'Sets a new password for a user')]
final class UserSetPasswordCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Username', suggestedValues: static function (): array {
            /** @var list<string> */
            return array_column(Sql::factory()->getArray('SELECT login FROM ' . Core::getTable('user')), 'login');
        })] string $user,
        #[Argument('Password')] ?string $password = null,
        #[Option('Require password change after login')] bool $passwordChangeRequired = false,
    ): int {
        $username = $user;

        $user = Sql::factory();
        $user
            ->setTable(Core::getTable('user'))
            ->setWhere(['login' => $username])
            ->select();

        if (!$user->getRows()) {
            throw new InvalidArgumentException(sprintf('User "%s" does not exist.', $username));
        }

        $user = User::fromSql($user);
        $id = $user->id;

        $passwordPolicy = BackendLogin::getPasswordPolicy();

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
            ->addGlobalUpdateFields(Environment::Console->value)
            ->setDateTimeValue('password_changed', time())
            ->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash))
            ->setValue('password_change_required', (int) $passwordChangeRequired)
            ->update();

        Extension::dispatch(new ExtensionPoint('PASSWORD_UPDATED', '', [
            'user_id' => $id,
            'user' => $user,
            'password' => $password,
        ], true));

        $io->success(sprintf('Saved new password for user "%s".', $username));

        return Command::SUCCESS;
    }
}
