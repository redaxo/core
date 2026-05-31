<?php

namespace Redaxo\Core\Console;

use Override;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Console\Command\AbstractCommand;
use Redaxo\Core\Console\Command\AvailableInSetupInterface;
use Redaxo\Core\Core;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

use function array_shift;
use function array_slice;
use function explode;
use function is_a;
use function sprintf;

/**
 * Discovers all commands that are marked with the {@see AsCommand} attribute and extend {@see AbstractCommand},
 * both in the core and in the active addons. Addons therefore register their commands simply by adding the
 * attribute to a command class.
 *
 * Commands are returned as {@see LazyCommand}, so that listing the commands (e.g. `console list`) does not
 * instantiate every command class — only the command that is actually executed is instantiated.
 *
 * @internal
 */
final class CommandLoader implements CommandLoaderInterface
{
    /** @var array<string, array{class: class-string<AbstractCommand>, name: string, aliases: list<string>, hidden: bool, description: string}> */
    private array $commands = [];

    public function __construct()
    {
        $isSetup = Core::isSetup();

        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsCommand::class, AbstractCommand::class) as $class => $attribute) {
            // Before the setup is completed only the explicitly marked commands are available.
            if ($isSetup && !is_a($class, AvailableInSetupInterface::class, true)) {
                continue;
            }

            // The name may contain aliases, separated by "|" (and an empty first segment for hidden commands).
            $names = explode('|', $attribute->name);
            $hidden = '' === $names[0];
            if ($hidden) {
                array_shift($names);
            }

            $command = [
                'class' => $class,
                'name' => $names[0],
                'aliases' => array_slice($names, 1),
                'hidden' => $hidden,
                'description' => $attribute->description ?? '',
            ];

            foreach ($names as $name) {
                $this->commands[$name] = $command;
            }
        }
    }

    #[Override]
    public function get(string $name): Command
    {
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $command = $this->commands[$name];
        $class = $command['class'];

        return new LazyCommand(
            $command['name'],
            $command['aliases'],
            $command['description'],
            $command['hidden'],
            static fn (): AbstractCommand => new $class(),
        );
    }

    #[Override]
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /** @return list<string> */
    #[Override]
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
