<?php

namespace Redaxo\Core\Console\Command;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\CronjobManager;
use Redaxo\Core\Database\Sql;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function define;
use function sprintf;

/**
 * @internal
 */
#[AsCommand(name: 'cronjob:run', description: 'Executes cronjobs of the "script" environment')]
class CronjobRunCommand extends AbstractCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('job', null, InputOption::VALUE_OPTIONAL, 'Execute single job (selected interactively or given by id)', false)
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        // indicator constant, kept for BC
        define('REX_CRONJOB_SCRIPT', true);

        $job = $input->getOption('job');

        if (false !== $job) {
            return $this->executeSingleJob($io, (int) $job);
        }

        $manager = CronjobManager::factory();

        $errors = 0;
        $manager->check(static function (string $name, bool $success, string $message) use ($io, &$errors) {
            /** @var int $errors */
            if ($success) {
                $io->success($name . ': ' . $message);
            } else {
                $io->error($name . ': ' . $message);
                ++$errors;
            }
        });

        /** @var int $errors */
        if ($errors) {
            $io->error('Cronjobs checked, ' . $errors . ' failed.');
            return Command::FAILURE;
        }

        $io->success('Cronjobs checked.');
        return Command::SUCCESS;
    }

    private function executeSingleJob(SymfonyStyle $io, ?int $id): int
    {
        $manager = CronjobManager::factory();

        if (null === $id) {
            $jobs = Sql::factory()->getArray('
                SELECT id, name
                FROM ' . Core::getTable('cronjob') . '
                WHERE environment LIKE "%|script|%"
                ORDER BY id
            ');
            $jobs = array_column($jobs, 'name', 'id');

            $question = new ChoiceQuestion('Which cronjob should be executed?', $jobs);
            $question->setValidator(static function ($selected) use ($jobs) {
                $selected = trim($selected);

                if (!isset($jobs[$selected])) {
                    throw new InvalidArgumentException(sprintf('Value "%s" is invalid.', $selected));
                }

                return $selected;
            });

            $id = $io->askQuestion($question);
            $name = $jobs[$id];
        } else {
            $name = $manager->getName($id);
        }

        $success = $manager->tryExecute($id);

        $msg = '';
        if ($manager->getMessage()) {
            $msg = ': ' . $manager->getMessage();
        }

        if ($success) {
            $io->success(sprintf('Cronjob "%s" executed successfully%s.', $name, $msg));

            return Command::SUCCESS;
        }

        $io->error(sprintf('Cronjob "%s" failed%s.', $name, $msg));

        return Command::FAILURE;
    }
}
