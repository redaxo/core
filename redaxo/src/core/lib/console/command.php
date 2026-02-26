<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package redaxo\core
 */
abstract class rex_console_command extends Command
{
    /** @var rex_package|null */
    protected $package;

    /** @return $this */
    public function setPackage(?rex_package $package = null)
    {
        $this->package = $package;

        return $this;
    }

    /** @return rex_package|null In core commands it returns `null`, otherwise the corresponding package object */
    public function getPackage()
    {
        return $this->package;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (TypeError $error) {
            $msg = $error->getMessage();

            // compat to symfony 4.x, where it wasn't required to return a status code from command::execute()
            // (redaxo < 5.12 uses symfony 4.x)
            if (str_starts_with($msg, 'Return value of "') && strpos($msg, '::execute()" must be of the type int,')) {
                return 0;
            }

            throw $error;
        }
    }

    /** @return SymfonyStyle */
    protected function getStyle(InputInterface $input, OutputInterface $output)
    {
        return new SymfonyStyle($input, $output);
    }

    /**
     * Decodes a html message for use in the CLI, e.g. provided by rex_i18n.
     *
     * @param string $message A html message
     *
     * @return string A cli optimzed message
     */
    protected function decodeMessage($message)
    {
        $message = preg_replace('/<br ?\/?>\r?\n?/', "\n", $message);
        $message = strip_tags($message);

        return htmlspecialchars_decode($message, ENT_QUOTES);
    }
}
