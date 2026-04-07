<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_array;

/**
 * @internal
 */
class ConfigGetCommand extends AbstractCommand implements StandaloneInterface
{
    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Get config variables')
            ->addArgument('config-key', InputArgument::REQUIRED, 'config path separated by periods, e.g. "setup" or "db.1.host"')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'php type of the returned value, e.g. "octal"', 'string')
            ->addOption('addon', 'p', InputOption::VALUE_REQUIRED, 'addon to inspect, defaults to redaxo-core', 'core')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);
        $key = $input->getArgument('config-key');
        $type = $input->getOption('type');

        if (!$key) {
            throw new InvalidArgumentException('config-key is required');
        }

        $path = explode('.', $key);
        $propertyKey = array_shift($path);

        $package = $input->getOption('addon');
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
