<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'addon:install', description: 'Installs the selected addon')]
final class AddonInstallCommand extends AbstractCommand
{
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument('The name of the addon, e.g. "yform"', suggestedValues: static function (): array {
            // allow all packages, because we support --re-intall for already installed ones
            return array_keys(Addon::getRegisteredAddons());
        })] string $addon,
        #[Option('Allows to reinstall the addon without asking the User', shortcut: 'r')] bool $reInstall = false,
    ): int {
        // the package manager don't know new packages in the addon folder
        // so we need to make them available
        AddonManager::synchronizeWithFileSystem();

        $package = Addon::get($addon);
        if (!$package) {
            $io->error('Addon "' . $addon . '" doesn\'t exists!');
            return Command::FAILURE;
        }

        if ($package->isInstalled() && !$reInstall) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Addon "' . $package->name . '" is already installed. Should it be reinstalled? (y/n) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $io->success('Addon "' . $package->name . '" wasn\'t reinstalled');
                return Command::SUCCESS;
            }
        }

        $manager = AddonManager::factory($package);
        $success = $manager->install();
        $message = $this->decodeMessage($manager->getMessage());

        if ($success) {
            $io->success($message);
            return Command::SUCCESS;
        }

        $io->error($message);
        return Command::FAILURE;
    }
}
