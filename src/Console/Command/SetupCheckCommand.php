<?php

namespace Redaxo\Core\Console\Command;

use PDOException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

use const PHP_VERSION;

/**
 * @internal
 */
#[AsCommand(name: 'setup:check', description: 'Check the commandline interface (CLI) environment for REDAXO requirements')]
class SetupCheckCommand extends AbstractCommand implements AvailableInSetupInterface
{
    public function __invoke(SymfonyStyle $io): int
    {
        $exitCode = 0;

        $errors = Setup::checkEnvironment();
        if (0 == count($errors)) {
            $io->success($this->decodeMessage(I18n::msg('setup_208', PHP_VERSION)));
        } else {
            $exitCode = 1;
            $errors = array_map($this->decodeMessage(...), $errors);
            $io->error("PHP version errors:\n" . implode("\n", $errors));
        }

        $res = Setup::checkFilesystem();
        if (count($res) > 0) {
            $errors = [];
            foreach ($res as $key => $messages) {
                if (count($messages) > 0) {
                    $affectedFiles = [];
                    foreach ($messages as $message) {
                        $affectedFiles[] = Path::relative($message);
                    }
                    $errors[] = I18n::msg($key) . "\n" . implode("\n", $affectedFiles);
                }
            }

            $exitCode = 2;
            $errors = array_map($this->decodeMessage(...), $errors);
            $io->error("Directory permissions error:\n" . implode("\n", $errors));
        } else {
            $io->success('Directory permissions ok');
        }

        $config = null;
        $configFile = Path::coreData('config.yml');
        if ($configFile) {
            $config = File::getConfig($configFile);
        }
        try {
            if ($config) {
                $err = Setup::checkDb($config, false);
            } else {
                $err = 'config.yml not found';
            }
            if ($err) {
                $exitCode = 3;
                $io->error("Database error:\n" . $this->decodeMessage($err));
            } else {
                $io->success('Database ok');
            }
        } catch (PDOException $e) {
            $exitCode = 3;
            $io->error("Database error:\n" . $e->getMessage());
        }

        return $exitCode;
    }
}
