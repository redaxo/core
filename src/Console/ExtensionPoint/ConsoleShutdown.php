<?php

namespace Redaxo\Core\Console\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @extends ExtensionPoint<null> */
final class ConsoleShutdown extends ExtensionPoint
{
    public const string NAME = 'CONSOLE_SHUTDOWN';

    public function __construct(
        public readonly Command $command,
        public readonly InputInterface $input,
        public readonly OutputInterface $output,
        public readonly int $exitCode,
    ) {
        parent::__construct(self::NAME, null, readonly: true);
    }
}
