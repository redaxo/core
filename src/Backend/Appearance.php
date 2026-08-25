<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\Core;
use Redaxo\Core\Env;

use function in_array;

/** Appearance of the backend. */
final class Appearance
{
    /** Theme (`light` or `dark`) forced for all users, overriding their personal setting. */
    public static ?string $forcedTheme = null;

    private function __construct() {}

    /**
     * Returns the theme to use: the forced one, otherwise the current user's setting.
     *
     * @return 'dark'|'light'|null `null` if the theme is up to the client (`prefers-color-scheme`)
     */
    public static function getTheme(): ?string
    {
        $themes = ['light', 'dark'];

        if (in_array(self::$forcedTheme, $themes, true)) {
            return self::$forcedTheme;
        }

        $userTheme = Core::getUser()?->theme;

        return in_array($userTheme, $themes, true) ? $userTheme : null;
    }

    /**
     * Returns the color used to visually mark this installation (top navbar border and mask icon),
     * defined by the env var `REX_INSTANCE_COLOR` (usually in the `.env` file).
     */
    public static function getInstanceColor(): ?string
    {
        return Env::get('REX_INSTANCE_COLOR');
    }
}
