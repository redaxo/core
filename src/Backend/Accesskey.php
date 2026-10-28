<?php

namespace Redaxo\Core\Backend;

/** Accesskeys of the backend, keyboard shortcuts for the most common actions. */
final class Accesskey
{
    public static bool $enabled = true;

    /** @var array<string, string> */
    public static array $keys = [
        'save' => 's',
        'apply' => 'x',
        'delete' => 'd',
        'add' => 'a',
        'add_2' => 'y',
    ];

    /** @var array<string, true> keys already given to an element of the current page */
    private static array $used = [];

    private function __construct() {}

    /**
     * Returns the accesskey for the given key, or `null` if accesskeys are disabled, the key has none or an earlier
     * element of the page already got it (an accesskey must be unique within the page, e.g. when several forms save).
     */
    public static function get(string $key): ?string
    {
        if (!self::$enabled || !isset(self::$keys[$key]) || isset(self::$used[$key])) {
            return null;
        }
        self::$used[$key] = true;

        return self::$keys[$key];
    }

    /**
     * Returns the title attribute for an element, including the accesskey attribute if the given key has one.
     *
     * @return non-empty-string
     */
    public static function attributes(string $title, string $key): string
    {
        $accesskey = self::get($key);

        if (null !== $accesskey) {
            return ' accesskey="' . $accesskey . '" title="' . $title . ' [' . $accesskey . ']"';
        }

        return ' title="' . $title . '"';
    }
}
