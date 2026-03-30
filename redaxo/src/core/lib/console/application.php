<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package redaxo\core
 */
class rex_console_application extends Application
{
    public function __construct()
    {
        parent::__construct('REDAXO', rex::getVersion());
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->checkConsoleUser($input, $output);
            return parent::doRun($input, $output);
        } catch (Exception $e) {
            // catch and rethrow \Exceptions first to only catch fatal errors below (\Exception implements \Throwable)
            throw $e;
        } catch (Throwable $e) {
            $message = $e->getMessage();

            if ($e instanceof ParseError) {
                $message = 'Parse error: ' . $message;
                $severity = E_PARSE;
            } elseif ($e instanceof TypeError) {
                $message = 'Type error: ' . $message;
                $severity = E_RECOVERABLE_ERROR;
            } else {
                $message = 'Fatal error: ' . $message;
                $severity = E_ERROR;
            }

            throw new ErrorException($message, 0, $severity, $e->getFile(), $e->getLine(), $e->getPrevious());
        }
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        if ($command instanceof rex_console_command) {
            $this->loadPackages($command);
        }

        $exitCode = parent::doRunCommand($command, $input, $output);

        rex_extension::registerPoint(new rex_extension_point_console_shutdown($command, $input, $output, $exitCode));

        return $exitCode;
    }

    /** @return void */
    private function loadPackages(rex_console_command $command)
    {
        // Some packages requires a working db connection in their boot.php
        // in this case if no connection is available, no commands can be used
        // but this command should be always usable
        if ($command instanceof rex_command_standalone) {
            return;
        }

        // Loads only setup packages
        // This is useful for any kind of pre-setup commands
        // there a packages which are needed during the setup e.g. backup
        if ($command instanceof rex_command_only_setup_packages) {
            if (rex::isSetup()) {
                foreach (rex_package::getSetupPackages() as $package) {
                    $package->enlist();
                }
            }
            foreach (rex_package::getSetupPackages() as $package) {
                $package->boot();
            }
            return;
        }

        if ('ydeploy:migrate' === $command->getName()) {
            // boot only the ydeploy package, which provides the migrate command
            $command->getPackage()->boot();

            return;
        }

        if (!rex::isSetup()) {
            // boot all known packages in the defined order
            // which reflects dependencies before consumers
            foreach (rex::getPackageOrder() as $packageId) {
                rex_package::require($packageId)->boot();
            }
        }

        rex_extension::registerPoint(new rex_extension_point('PACKAGES_INCLUDED'));
    }

    private function checkConsoleUser(InputInterface $input, OutputInterface $output): bool
    {
        $io = new SymfonyStyle($input, $output);

        if (function_exists('posix_getuid')) {
            $currentuser = posix_getpwuid(posix_getuid());
            $webuser = posix_getpwuid(fileowner(rex_path::backend()));
            if ($currentuser['name'] !== $webuser['name']) {
                $io->warning([
                    'Current user: ' . $currentuser['name'] . "\nOwner of redaxo: " . $webuser['name'],
                    'Running the console with a different user might cause unexpected side-effects.',
                ]);
                return false;
            }
        }

        return true;
    }
}
