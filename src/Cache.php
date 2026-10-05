<?php

namespace Redaxo\Core;

use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;

use function function_exists;
use function get_declared_classes;

final class Cache
{
    private function __construct() {}

    /** Deletes the cache. */
    public static function delete(): string
    {
        // close logger, so the logfile can also be deleted
        Logger::close();

        $finder = Finder::factory(Path::cache())
            ->recursive()
            ->childFirst()
            ->ignoreSystemStuff(false)
            ->ignoreUnreadableDirs();
        Dir::deleteIterator($finder);

        self::reset();

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // ----- EXTENSION POINT
        return Extension::dispatch(new ExtensionPoint('CACHE_DELETED', I18n::msg('delete_cache_message')));
    }

    /**
     * Discards the data held in memory by the current process (instance pools, languages), so it is reloaded on
     * next access.
     *
     * Long-running processes like queue workers or websocket servers call this to pick up changes made by other
     * processes.
     */
    public static function reset(): void
    {
        Language::reset();

        // Only classes declared so far can hold pooled instances. The pools live in the class using the trait,
        // clearing it there covers its subclasses as well.
        $clearers = [
            InstancePoolTrait::class => 'clearInstancePool',
            InstanceListPoolTrait::class => 'clearInstanceListPool',
        ];
        foreach (get_declared_classes() as $class) {
            foreach (class_uses($class) ?: [] as $trait) {
                if (isset($clearers[$trait])) {
                    [$class, $clearers[$trait]]();
                }
            }
        }
    }
}
