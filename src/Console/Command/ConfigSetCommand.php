<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Util\Type;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function in_array;
use function is_array;

/**
 * @internal
 */
#[AsCommand(
    name: 'config:set',
    description: 'Set config variables',
    help: <<<'EOF'
        Set config variables in config.yml.

        Example: enable setup
          <info>%command.full_name% --type boolean setup true</info>

        Example: set password min length to 8
          <info>%command.full_name% --type integer password_policy.length.min 8</info>

        Example: set error email
          <info>%command.full_name% error_email mail@example.org</info>
        EOF,
)]
class ConfigSetCommand extends AbstractCommand implements StandaloneInterface, AvailableInSetupInterface
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('config-key', InputArgument::REQUIRED, 'config path separated by periods, e.g. "setup" or "db.1.host"')
            ->addArgument('value', InputArgument::OPTIONAL, 'new value for config key, e.g. "somestring" or "1"')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'php type of new value, e.g. "bool", "octal" or "int"', 'string')
            ->addOption('unset', null, InputOption::VALUE_NONE, 'sets the config key to null')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $key = $input->getArgument('config-key');
        $value = $input->getArgument('value');
        $unset = $input->getOption('unset');
        $type = $input->getOption('type');

        if (null === $value && false === $unset) {
            throw new InvalidArgumentException('No new value specified');
        }

        if ($unset) {
            $value = null;
        } elseif ('bool' === $type || 'boolean' === $type) {
            $value = in_array($value, ['true', 'on', '1'], true) ? true : $value;
            $value = in_array($value, ['false', 'off', '0'], true) ? false : $value;
        } elseif ('octal' === $type) {
            // turns e.g. 755 into 0755
            // a leading zero marks a octal-string
            $value = '0' . $value;
        } else {
            $value = Type::cast($value, $type);
        }

        $path = explode('.', $key);

        $configFile = Path::coreData('config.yml');
        $baseConfig = File::getConfig($configFile);
        $config = &$baseConfig;

        foreach ($path as $i => $pathPart) {
            if (!isset($config[$pathPart]) || !is_array($config[$pathPart])) {
                $config[$pathPart] = [];
            }
            if ($i === count($path) - 1) {
                $config[$pathPart] = $value;
                break;
            }
            $config = &$config[$pathPart];
        }

        if (File::putConfig($configFile, $baseConfig)) {
            $io->success('Config variable successfully saved.');
            return Command::SUCCESS;
        }

        $io->error("Config variable couldn't be saved.");
        return Command::FAILURE;
    }
}
