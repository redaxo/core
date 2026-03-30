<?php

use Symfony\Component\Console\Exception\InvalidArgumentException as SymfonyInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package redaxo\cronjob
 *
 * @internal
 */
class rex_command_cronjob_run extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Executes cronjobs of the "script" environment')
            ->addOption('job', null, InputOption::VALUE_OPTIONAL, 'Execute single job (selected interactively or given by id)', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        // indicator constant, kept for BC
        define('REX_CRONJOB_SCRIPT', true);

        $job = $input->getOption('job');

        if (false !== $job) {
            return $this->executeSingleJob($io, $job);
        }

        $manager = rex_cronjob_manager_sql::factory();

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
            return 1;
        }

        $io->success('Cronjobs checked.');
        return 0;
    }

    /** @return int */
    private function executeSingleJob(SymfonyStyle $io, $id)
    {
        $manager = rex_cronjob_manager_sql::factory();

        if (null === $id) {
            $jobs = rex_sql::factory()->getArray('
                SELECT id, name
                FROM ' . rex::getTable('cronjob') . '
                WHERE environment LIKE "%|script|%"
                ORDER BY id
            ');
            $jobs = array_column($jobs, 'name', 'id');

            $question = new ChoiceQuestion('Which cronjob should be executed?', $jobs);
            $question->setValidator(static function ($selected) use ($jobs) {
                $selected = trim($selected);

                if (!isset($jobs[$selected])) {
                    throw new SymfonyInvalidArgumentException(sprintf('Value "%s" is invalid.', $selected));
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
        if ($manager->hasMessage()) {
            $msg = ': ' . $manager->getMessage();
        }

        if ($success) {
            $io->success(sprintf('Cronjob "%s" executed successfully%s.', $name, $msg));

            return 0;
        }

        $io->error(sprintf('Cronjob "%s" failed%s.', $name, $msg));

        return 1;
    }
}
