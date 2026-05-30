<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'user:list', description: 'List all users or a specific user by login name')]
final class UserListCommand extends AbstractCommand
{
    public function __invoke(
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument('Username', suggestedValues: static function (): array {
            /** @var list<string> */
            return array_column(Sql::factory()->getArray('SELECT login FROM ' . Core::getTable('user')), 'login');
        })] ?string $user = null,
    ): int {
        $sql = Sql::factory();
        $query = '
            SELECT
                IF(name <> "", name, login) as name,
                `login`,
                `email`,
                IF(`admin`, "Admin", IFNULL((SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR ", ") FROM ' . Core::getTable('user_role') . ' r WHERE FIND_IN_SET(r.id, role)), "")) as role,
                `createdate`,
                `lastlogin`
            FROM ' . Core::getTable('user') . '
        ';
        if ($user) {
            $sql->setQuery($query . ' WHERE login = :login', [
                'login' => $user,
            ]);

            if (0 === $sql->getRows()) {
                $io->error(sprintf('The user "%s" does not exist.', $user));
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
