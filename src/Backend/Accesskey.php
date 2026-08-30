<?php

namespace Redaxo\Core\Backend;

use function array_key_exists;

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

    private function __construct() {}

    /**
     * Returns the title attribute for an element, including the accesskey attribute if the given key has one.
     *
     * @return non-empty-string
     */
    public static function attributes(string $title, string $key): string
    {
        if (self::$enabled && array_key_exists($key, self::$keys)) {
            $accesskey = self::$keys[$key];

            return ' accesskey="' . $accesskey . '" title="' . $title . ' [' . $accesskey . ']"';
        }

        return ' title="' . $title . '"';
    }
}
