<?php

namespace Redaxo\Core\Console;

use ErrorException;
use Exception;
use Override;
use ParseError;
use Redaxo\Core\AbstractProject;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Console\Command\AbstractCommand;
use Redaxo\Core\Console\Command\OnlySetupAddonsInterface;
use Redaxo\Core\Console\Command\StandaloneInterface;
use Redaxo\Core\Console\ExtensionPoint\ConsoleShutdown;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TypeError;

use function function_exists;

use const E_ERROR;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;

class Application extends SymfonyApplication
{
    public function __construct(
        private readonly AbstractProject $project,
    ) {
        parent::__construct('REDAXO', Core::getVersion());
    }

    #[Override]
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

    #[Override]
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        // Unwrap the LazyCommand returned by the CommandLoader to get the actual command instance.
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        if ($command instanceof AbstractCommand) {
            $this->loadPackages($command);
        }

        $exitCode = parent::doRunCommand($command, $input, $output);

        Extension::registerPoint(new ConsoleShutdown($command, $input, $output, $exitCode));

        return $exitCode;
    }

    private function loadPackages(AbstractCommand $command): void
    {
        // Some packages require a working db connection in their boot.php
        // in this case if no connection is available, no commands can be used
        // but this command should be always usable
        if ($command instanceof StandaloneInterface) {
            return;
        }

        // Loads only setup packages
        // This is useful for any kind of pre-setup commands
        // there a packages which are needed during the setup e.g. backup
        if ($command instanceof OnlySetupAddonsInterface) {
            if (Core::isSetup()) {
                foreach (Addon::getSetupAddons() as $package) {
                    $package->enlist();
                }
            }
            foreach (Addon::getSetupAddons() as $package) {
                $package->boot();
            }
            return;
        }

        if ('ydeploy:migrate' === $command->getName()) {
            // boot only the ydeploy package, which provides the migrate command
            $command->addon->boot();

            return;
        }

        if (!Core::isSetup()) {
            // boot all known packages in the defined order
            // which reflects dependencies before consumers
            foreach (Core::getPackageOrder() as $packageId) {
                Addon::require($packageId)->boot();
            }
        }

        Extension::registerByAttribute($this->project);

        Extension::registerPoint(new ExtensionPoint('PACKAGES_INCLUDED'));

        $this->project->boot();
    }

    private function checkConsoleUser(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if (!function_exists('posix_getuid')) {
            return;
        }

        $currentuser = posix_getpwuid(posix_getuid());
        $webuser = posix_getpwuid(fileowner(Path::backend()));
        if ($currentuser['name'] !== $webuser['name']) {
            $io->warning([
                'Current user: ' . $currentuser['name'] . "\nOwner of redaxo: " . $webuser['name'],
                'Running the console with a different user might cause unexpected side-effects.',
            ]);
        }
    }
}
