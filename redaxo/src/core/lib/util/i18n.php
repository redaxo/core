<?php

/**
 * Class for internationalization.
 *
 * @package redaxo\core
 */
class rex_i18n
{
    /** @var list<string> */
    private static array $locales = [];
    /** @var list<string> */
    private static array $directories = [];
    /** @var array<string, bool> Holds which locales are loaded. keyed by locale */
    private static array $loaded = [];
    private static ?string $locale = null;
    /** @var array<string, array<string, non-empty-string>> */
    private static array $msg = [];

    /**
     * Switches the current locale.
     *
     * @param string $locale The new locale
     * @param bool $phpSetLocale When TRUE, php function setlocale() will be called
     *
     * @return string The last locale
     */
    public static function setLocale($locale, $phpSetLocale = true)
    {
        $saveLocale = self::getLocale();
        self::$locale = self::validateLocale($locale);

        if (empty(self::$loaded[$locale])) {
            self::loadAll($locale);
        }

        if ($phpSetLocale) {
            [$lang, $country] = explode('_', self::getLocale(), 2);

            // In setup we want to reach the php extensions check even if intl extension is missing
            if (class_exists(Locale::class)) {
                Locale::setDefault($lang . '-' . strtoupper($country));
            }

            $locales = [];
            foreach (explode(',', trim(self::msg('setlocale'))) as $locale) {
                $locales[] = $locale . '.UTF-8';
                $locales[] = $locale . '.UTF8';
                $locales[] = $locale . '.utf-8';
                $locales[] = $locale . '.utf8';
                $locales[] = $locale;
            }

            setlocale(LC_ALL, $locales);
        }

        return $saveLocale;
    }

    /**
     * Returns the current locale, e.g. de_de.
     *
     * @return non-empty-string The current locale
     */
    public static function getLocale()
    {
        if (!self::$locale) {
            self::$locale = self::validateLocale(rex::getProperty('lang'));
        }

        return self::$locale;
    }

    /**
     * Returns the current language, e.g. "de".
     *
     * @return non-empty-string The current language
     */
    public static function getLanguage()
    {
        [$lang, $country] = explode('_', self::getLocale(), 2);
        return $lang;
    }

    /**
     * Adds a directory with lang files.
     *
     * @param string $dir Path to the directory
     * @return void
     */
    public static function addDirectory($dir)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        if (in_array($dir, self::$directories, true)) {
            return;
        }

        self::$directories[] = $dir;

        foreach (self::$loaded as $locale => $_) {
            self::loadFile($dir, $locale);
        }
    }

    /**
     * Returns the translation htmlspecialchared for the given key.
     *
     * @param string $key A Language-Key
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     *
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape html
     */
    public static function msg($key, ...$replacements)
    {
        return self::getMsg($key, true, func_get_args());
    }

    /**
     * Returns the translation for the given key.
     *
     * @param string $key A Language-Key
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     *
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-specialize
     */
    public static function rawMsg($key, ...$replacements)
    {
        return self::getMsg($key, false, func_get_args());
    }

    /**
     * Returns the translation htmlspecialchared for the given key and locale.
     *
     * @param string $key A Language-Key
     * @param string $locale A Locale
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     *
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape html
     */
    public static function msgInLocale($key, $locale, ...$replacements)
    {
        $args = func_get_args();
        $args[1] = $key;
        // for BC we need to strip the 1st arg
        array_shift($args);
        return self::getMsg($key, true, $args, $locale);
    }

    /**
     * Returns the translation for the given key and locale.
     *
     * @param string $key A Language-Key
     * @param string $locale A Locale
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     *
     * @return non-empty-string Translation for the key
     */
    public static function rawMsgInLocale($key, $locale, ...$replacements)
    {
        $args = func_get_args();
        $args[1] = $key;
        // for BC we need to strip the 1st arg
        array_shift($args);
        return self::getMsg($key, false, $args, $locale);
    }

    /**
     * Returns the message fallback for a missing key in main locale.
     *
     * @param string $key A Language-Key
     * @param list<string|int> $replacements A arbritary number of strings/ints used for interpolating within the resolved message
     * @param string $locale A Locale
     *
     * @return non-empty-string
     */
    private static function getMsgFallback($key, array $replacements, $locale)
    {
        $fallback = "[translate:$key]";

        $msg = rex_extension::registerPoint(new rex_extension_point('I18N_MISSING_TRANSLATION', $fallback, [
            'key' => $key,
            'args' => $replacements,
        ]));

        if ($msg !== $fallback) {
            return $msg;
        }

        foreach (rex::getProperty('lang_fallback', []) as $fallbackLocale) {
            if ($locale === $fallbackLocale) {
                continue;
            }

            if (empty(self::$loaded[$fallbackLocale])) {
                self::loadAll($fallbackLocale);
            }

            if (isset(self::$msg[$fallbackLocale][$key])) {
                return self::$msg[$fallbackLocale][$key];
            }
        }

        return $fallback;
    }

    /**
     * Checks if there is a translation for the given key.
     *
     * @param string $key Key
     *
     * @return bool TRUE on success, else FALSE
     */
    public static function hasMsg($key)
    {
        return isset(self::$msg[self::getLocale()][$key]);
    }

    /**
     * Returns the translation for the given key.
     *
     * @param string $key
     * @param bool $escape
     * @param list<string|int> $replacements A arbritary number of strings/ints used for interpolating within the resolved message
     * @param string $locale A Locale
     *
     * @psalm-taint-escape ($escape is true ? "html" : null)
     *
     * @return non-empty-string
     */
    private static function getMsg($key, $escape, array $replacements, $locale = null)
    {
        if (!$locale) {
            $locale = self::getLocale();
        }

        if (empty(self::$loaded[$locale])) {
            self::loadAll($locale);
        }

        if (isset(self::$msg[$locale][$key])) {
            $msg = self::$msg[$locale][$key];
        } else {
            $msg = self::getMsgFallback($key, $replacements, $locale);
        }

        $patterns = [];
        $replaceWith = [];
        $argNum = count($replacements);
        if ($argNum > 1) {
            for ($i = 1; $i < $argNum; ++$i) {
                // zero indexed
                $patterns[] = '/\{' . ($i - 1) . '\}/';
                $replaceWith[] = (string) $replacements[$i];
            }
        }

        $msg = preg_replace($patterns, $replaceWith, $msg);
        if (null === $msg) {
            throw new rex_exception(preg_last_error_msg());
        }

        if ($escape) {
            $msg = rex_escape($msg, 'html_simplified');
        }

        return $msg;
    }

    /**
     * Checks if there is a translation for the given key in current language or any fallback language.
     *
     * @param string $key Key
     *
     * @return bool TRUE on success, else FALSE
     */
    public static function hasMsgOrFallback($key)
    {
        $currentLocale = self::getLocale();

        if (isset(self::$msg[$currentLocale][$key])) {
            return true;
        }

        foreach (rex::getProperty('lang_fallback', []) as $locale) {
            if ($currentLocale === $locale) {
                continue;
            }

            if (empty(self::$loaded[$locale])) {
                self::loadAll($locale);
            }

            if (isset(self::$msg[$locale][$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a new translation to the catalogue.
     *
     * @param string $key Key
     * @param non-empty-string $message Message for the key
     * @return void
     */
    public static function addMsg($key, $message)
    {
        self::$msg[self::getLocale()][$key] = $message;
    }

    /**
     * Returns the locales.
     *
     * @return list<string> Array of Locales
     */
    public static function getLocales()
    {
        if (empty(self::$locales) && isset(self::$directories[0]) && is_readable(self::$directories[0])) {
            self::$locales = [];

            foreach (rex_finder::factory(self::$directories[0])->filesOnly() as $file) {
                if (preg_match('/^(\\w+)\\.lang$/', $file->getFilename(), $matches)) {
                    self::$locales[] = $matches[1];
                }
            }
        }

        return self::$locales;
    }

    /**
     * Translates the $text, if it begins with 'translate:', else it returns $text.
     *
     * @param string $text The text for translation
     * @param bool $escape Flag whether the translated text should be escaped
     * @param callable(string):string|null $i18nFunction Function that returns the translation for the i18n key
     *
     * @throws InvalidArgumentException
     *
     * @psalm-taint-escape ($escape is true ? "html" : null)
     * @psalm-taint-specialize
     *
     * @return non-empty-string Translated text
     */
    public static function translate($text, $escape = true, ?callable $i18nFunction = null)
    {
        if (!is_string($text)) {
            throw new InvalidArgumentException('Expecting $text to be a String, "' . gettype($text) . '" given!');
        }

        $tranKey = 'translate:';
        $transKeyLen = strlen($tranKey);
        if (substr($text, 0, $transKeyLen) == $tranKey) {
            if (!$i18nFunction) {
                if ($escape) {
                    return self::msg(substr($text, $transKeyLen));
                }
                return self::rawMsg(substr($text, $transKeyLen));
            }
            // cuf() required for php5 compat to support 'class::method' like callables
            return call_user_func($i18nFunction, substr($text, $transKeyLen));
        }
        if ($escape) {
            return rex_escape($text);
        }
        return $text;
    }

    /**
     * Translates all array elements.
     *
     * @param mixed $array The Array of Strings for translation
     * @param bool $escape Flag whether the translated text should be escaped
     * @param callable $i18nFunction Function that returns the translation for the i18n key
     *
     * @throws InvalidArgumentException
     *
     * @psalm-taint-escape ($escape is true ? "html" : null)
     *
     * @return mixed
     */
    public static function translateArray($array, $escape = true, ?callable $i18nFunction = null)
    {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_string($value)) {
                    $array[$key] = self::translate($value, $escape, $i18nFunction);
                } else {
                    $array[$key] = self::translateArray($value, $escape, $i18nFunction);
                }
            }
            return $array;
        }
        if (is_string($array)) {
            return self::translate($array, $escape, $i18nFunction);
        }
        if (null === $array || is_scalar($array)) {
            return $array;
        }
        throw new InvalidArgumentException('Expecting $text to be a String or Array of Scalar, "' . gettype($array) . '" given!');
    }

    /**
     * Loads the translation definitions of the given file.
     *
     * @param string $dir Path to the directory
     * @param string $locale Locale
     * @return void
     */
    private static function loadFile($dir, $locale)
    {
        $locale = self::validateLocale($locale);

        $file = $dir . DIRECTORY_SEPARATOR . $locale . '.lang';
        if (!($content = rex_file::get($file))) {
            return;
        }
        if (!preg_match_all('/^([^=\s]+)\h*=\h*(\S.*)(?<=\S)/m', $content, $matches, PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $match) {
            self::$msg[$locale][$match[1]] = $match[2];
        }
    }

    /**
     * Loads all translation defintions.
     *
     * @param string $locale Locale
     * @return void
     */
    private static function loadAll($locale)
    {
        foreach (self::$directories as $dir) {
            self::loadFile($dir, $locale);
        }

        self::$loaded[$locale] = true;
    }

    /**
     * @param string $locale Locale
     *
     * @return non-empty-string the validated locale
     *
     * @psalm-taint-escape file
     * @psalm-taint-escape include
     */
    private static function validateLocale(string $locale): string
    {
        if (!$locale || !preg_match('/^[a-z]{2}_[a-z]{2}$/', $locale)) {
            throw new rex_exception('Invalid locale "' . $locale . '"');
        }
        return $locale;
    }
}
