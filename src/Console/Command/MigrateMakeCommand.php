<?php

namespace Redaxo\Core\Console\Command;

use DateTimeImmutable;
use DateTimeZone;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Migration\Migration;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Util\Str;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_keys;
use function array_search;
use function is_dir;
use function is_file;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
#[AsCommand(name: 'migrate:make', description: 'Creates a new migration file')]
final class MigrateMakeCommand extends AbstractCommand implements StandaloneInterface
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Short description of what the migration does, used for the file name')] string $description = '',
        #[Option('Package the migration belongs to', suggestedValues: static function (): array {
            return MigrateMakeCommand::getPackages();
        })] string $package = Migrator::PROJECT,
    ): int {
        $packages = self::getPackages();
        $index = array_search($package, $packages, true);

        if (false === $index) {
            throw new InvalidArgumentException(sprintf('Unknown package "%s".', $package));
        }

        // UTC, so migrations written by developers in different timezones still end up in the intended order.
        $id = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d-His');

        // The dash keeps the timestamp block visually separate from the name, whose own words stay underscored.
        if ('' !== $slug = Str::normalize($description)) {
            $id .= '-' . $slug;
        }

        $directory = Migrator::getDirectory($packages[$index]);

        if (!is_dir($directory) && !Dir::create($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" could not be created.', $directory));
        }

        $path = $directory . DIRECTORY_SEPARATOR . $id . '.php';

        if (is_file($path)) {
            throw new RuntimeException(sprintf('File "%s" already exists.', $path));
        }

        if (!File::put($path, self::getStub())) {
            throw new RuntimeException(sprintf('File "%s" could not be written.', $path));
        }

        $io->success(sprintf('Created migration "%s".', $path));

        return Command::SUCCESS;
    }

    /** @return list<non-empty-string> */
    public static function getPackages(): array
    {
        return [Migrator::PROJECT, Migrator::CORE, ...array_keys(Addon::getInstalledAddons())];
    }

    private static function getStub(): string
    {
        return '<?php' . "\n"
            . "\n"
            . 'use ' . Migration::class . ';' . "\n"
            . "\n"
            . 'return new class extends Migration {' . "\n"
            . '    public function up(): void' . "\n"
            . '    {' . "\n"
            . '    }' . "\n"
            . '};' . "\n";
    }
}
