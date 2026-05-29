<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;

/**
 * @internal
 */
#[AsCommand(name: 'config:get', description: 'Get config variables')]
class ConfigGetCommand extends AbstractCommand implements StandaloneInterface, AvailableInSetupInterface
{
    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument('config path separated by periods, e.g. "setup" or "db.1.host"')] string $key,
        #[Option('php type of the returned value, e.g. "octal"', shortcut: 't')] string $type = 'string',
        #[Option('addon to inspect, defaults to redaxo-core', name: 'addon', shortcut: 'p')] string $package = 'core',
    ): int {
        if (!$key) {
            throw new InvalidArgumentException('config-key is required');
        }

        $path = explode('.', $key);
        $propertyKey = array_shift($path);

        if ('core' === $package) {
            $config = Core::getProperty($propertyKey);
        } else {
            $addon = Addon::require($package);
            $config = match ($propertyKey) {
                'author' => $addon->getAuthor(),
                'version' => $addon->getVersion(),
                'supportpage' => $addon->getSupportPage(),
                'license' => $addon->getLicense(),
                default => $addon->getProperty($propertyKey),
            };
        }

        if (null === $config) {
            $io->getErrorStyle()->error('Config key not found');
            return Command::FAILURE;
        }
        foreach ($path as $pathPart) {
            if (!is_array($config) || !isset($config[$pathPart])) {
                $io->getErrorStyle()->error('Config key not found');
                return Command::FAILURE;
            }
            $config = $config[$pathPart];
        }

        if ('octal' === $type) {
            // turn fileperm/dirperm into the expected values like e.g. 755
            $output->writeln(decoct($config));
        } else {
            $output->writeln(json_encode($config));
        }

        return Command::SUCCESS;
    }
}
