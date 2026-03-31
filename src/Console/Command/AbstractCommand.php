<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use const ENT_QUOTES;

abstract class AbstractCommand extends Command
{
    protected ?Addon $addon = null;

    /** @internal */
    public function setAddon(?Addon $addon = null): void
    {
        $this->addon = $addon;
    }

    /** @return Addon|null In core commands it returns `null`, otherwise the corresponding addon object */
    public function getAddon(): ?Addon
    {
        return $this->addon;
    }

    protected function getStyle(InputInterface $input, OutputInterface $output): SymfonyStyle
    {
        return new SymfonyStyle($input, $output);
    }

    /**
     * Decodes a html message for use in the CLI, e.g. provided by I18n.
     *
     * @param string $message A html message
     *
     * @return string A cli optimized message
     */
    protected function decodeMessage(string $message): string
    {
        $message = preg_replace('/<br ?\/?>\r?\n?/', "\n", $message);
        $message = strip_tags($message);

        return htmlspecialchars_decode($message, ENT_QUOTES);
    }
}
