<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Addon\Addon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class AddonRunUpdateScriptCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Runs the update.php of the given addon with given previous version')
            ->addArgument('addon-id', InputArgument::REQUIRED, 'The id of the addon, e.g. "yform"', null, static function () {
                $packageNames = [];

                foreach (Addon::getRegisteredAddons() as $package) {
                    if (!$package->isInstalled()) {
                        continue;
                    }

                    $packageNames[] = $package->name;
                }

                return $packageNames;
            })
            ->addArgument('previous-version', InputArgument::REQUIRED, 'The previous addon version that is used for version comparisons in update.php')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $packageId = $input->getArgument('addon-id');

        $package = Addon::get($packageId);
        if (!$package->isInstalled()) {
            $io->error('Addon "' . $packageId . '" is not installed!');
            return Command::FAILURE;
        }

        $version = $package->getVersion();
        $package->setProperty('version', $input->getArgument('previous-version'));

        try {
            $package->includeFile(Addon::FILE_UPDATE);
        } finally {
            $package->setProperty('version', $version);
        }

        if ('' !== ($message = (string) $package->getProperty('updatemsg', ''))) {
            $io->error($message);
            return Command::FAILURE;
        }
        if (!$package->getProperty('update', true)) {
            $io->error('Failed without a given reason.');
            return Command::FAILURE;
        }

        $io->success('Successfully executed the update.php.');
        return Command::SUCCESS;
    }
}
