<?php

namespace Redaxo\Core\Console\Command;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Exception\LogicException;
use ReflectionObject;
use Symfony\Component\Console\Command\Command;

use function sprintf;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;
use const ENT_QUOTES;

abstract class AbstractCommand extends Command
{
    /**
     * The addon the command belongs to, resolved lazily from the location of the command class.
     *
     * Only available for addon commands; reading it on a core command throws a {@see LogicException}.
     */
    public private(set) Addon $addon {
        get => $this->addon ??= $this->resolveAddon();
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

    private function resolveAddon(): Addon
    {
        $file = new ReflectionObject($this)->getFileName();

        if (false !== $file) {
            $file = realpath($file) ?: $file;

            foreach (Addon::getActivatedAddons() as $addon) {
                if (str_starts_with($file, $addon->path . DIRECTORY_SEPARATOR)) {
                    return $addon;
                }
            }
        }

        throw new LogicException(sprintf('Command "%s" does not belong to an addon.', $this::class));
    }
}
