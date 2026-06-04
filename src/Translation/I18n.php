<?php

namespace Redaxo\Core\Translation;

use Locale;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;

use function call_user_func;
use function count;
use function func_get_args;
use function in_array;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const LC_ALL;
use const PREG_SET_ORDER;

/**
 * Class for internationalization.
 */
final class I18n
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

    private function __construct() {}

    /**
     * Switches the current locale.
     *
     * @param string $locale The new locale
     * @param bool $phpSetLocale When TRUE, php function setlocale() will be called
     *
     * @return string The last locale
     */
    public static function setLocale(string $locale, bool $phpSetLocale = true): string
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
    public static function getLocale(): string
    {
        if (!self::$locale) {
            self::$locale = self::validateLocale(Core::getProperty('lang'));
        }

        return self::$locale;
    }

    /**
     * Returns the current language, e.g. "de".
     *
     * @return non-empty-string The current language
     */
    public static function getLanguage(): string
    {
        [$lang, $country] = explode('_', self::getLocale(), 2);
        return $lang;
    }

    /** Adds a directory with lang files. */
    public static function addDirectory(string $dir): void
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
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape html
     */
    public static function msg(string $key, string|int ...$replacements): string
    {
        return self::getMsg($key, true, func_get_args());
    }

    /**
     * Returns the translation for the given key.
     *
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-specialize
     */
    public static function rawMsg(string $key, string|int ...$replacements): string
    {
        return self::getMsg($key, false, func_get_args());
    }

    /**
     * Returns the translation htmlspecialchared for the given key and locale.
     *
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     * @return non-empty-string Translation for the key
     *
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape html
     */
    public static function msgInLocale(string $key, string $locale, string|int ...$replacements): string
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
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     * @return non-empty-string Translation for the key
     */
    public static function rawMsgInLocale(string $key, string $locale, string|int ...$replacements): string
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
     * @param list<string|int> $replacements A arbritary number of strings/ints used for interpolating within the resolved message
     * @return non-empty-string
     */
    private static function getMsgFallback(string $key, array $replacements, string $locale): string
    {
        $fallback = "[translate:$key]";

        $msg = Extension::dispatch(new ExtensionPoint('I18N_MISSING_TRANSLATION', $fallback, [
            'key' => $key,
            'args' => $replacements,
        ]));

        if ($msg !== $fallback) {
            return $msg;
        }

        foreach (Core::getProperty('lang_fallback', []) as $fallbackLocale) {
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

    /** Checks if there is a translation for the given key. */
    public static function hasMsg(string $key): bool
    {
        return isset(self::$msg[self::getLocale()][$key]);
    }

    /**
     * Returns the translation for the given key.
     *
     * @param list<string|int> $replacements A arbritary number of strings/ints used for interpolating within the resolved message
     *
     * @psalm-taint-escape ($escape is true ? "html" : null)
     *
     * @return non-empty-string
     */
    private static function getMsg(string $key, bool $escape, array $replacements, ?string $locale = null): string
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
            throw new RuntimeException(preg_last_error_msg());
        }

        if ($escape) {
            $msg = escape($msg, 'html_simplified');
        }

        return $msg;
    }

    /** Checks if there is a translation for the given key in current language or any fallback language. */
    public static function hasMsgOrFallback(string $key): bool
    {
        $currentLocale = self::getLocale();

        if (isset(self::$msg[$currentLocale][$key])) {
            return true;
        }

        foreach (Core::getProperty('lang_fallback', []) as $locale) {
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
     * @param non-empty-string $message Message for the key
     */
    public static function addMsg(string $key, string $message): void
    {
        self::$msg[self::getLocale()][$key] = $message;
    }

    /**
     * Returns the locales.
     *
     * @return list<string> Array of Locales
     */
    public static function getLocales(): array
    {
        if (empty(self::$locales) && isset(self::$directories[0]) && is_readable(self::$directories[0])) {
            self::$locales = [];

            foreach (Finder::factory(self::$directories[0])->filesOnly() as $file) {
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
     * @psalm-taint-escape ($escape is true ? "html" : null)
     * @psalm-taint-specialize
     *
     * @return non-empty-string Translated text
     */
    public static function translate(string $text, bool $escape = true, ?callable $i18nFunction = null): string
    {
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
            return escape($text);
        }
        return $text;
    }

    /**
     * Translates all array elements.
     *
     * @param scalar|array<scalar|array<mixed>> $array The Array of Strings for translation
     * @param bool $escape Flag whether the translated text should be escaped
     * @param callable|null $i18nFunction Function that returns the translation for the i18n key
     *
     * @psalm-taint-escape ($escape is true ? "html" : null)
     *
     * @return scalar|array<scalar|array<mixed>>
     */
    public static function translateArray(array|string|int|float|bool|null $array, bool $escape = true, ?callable $i18nFunction = null): array|string|int|float|bool|null
    {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_string($value)) {
                    $array[$key] = self::translate($value, $escape, $i18nFunction);
                } else {
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $array[$key] = self::translateArray($value, $escape, $i18nFunction);
                }
            }
            /** @var scalar|array<scalar|array<mixed>> */
            return $array; // @phpstan-ignore varTag.nativeType
        }
        if (is_string($array)) {
            return self::translate($array, $escape, $i18nFunction);
        }

        return $array; // scalar
    }

    /** Loads the translation definitions of the given file. */
    private static function loadFile(string $dir, string $locale): void
    {
        $locale = self::validateLocale($locale);

        $file = $dir . DIRECTORY_SEPARATOR . $locale . '.lang';
        if (!($content = File::get($file))) {
            return;
        }
        if (!preg_match_all('/^([^=\s]+)\h*=\h*(\S.*)(?<=\S)/m', $content, $matches, PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $match) {
            self::$msg[$locale][$match[1]] = $match[2];
        }
    }

    /** Loads all translation defintions. */
    private static function loadAll(string $locale): void
    {
        foreach (self::$directories as $dir) {
            self::loadFile($dir, $locale);
        }

        self::$loaded[$locale] = true;
    }

    /**
     * @return non-empty-string the validated locale
     *
     * @psalm-taint-escape file
     * @psalm-taint-escape include
     */
    private static function validateLocale(string $locale): string
    {
        if (!$locale || !preg_match('/^[a-z]{2}_[a-z]{2}$/', $locale)) {
            throw new InvalidArgumentException('Invalid locale "' . $locale . '"');
        }
        return $locale;
    }
}
