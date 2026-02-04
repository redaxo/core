<?php

use Composer\Autoload\ClassLoader;

/**
 * REDAXO Autoloader.
 *
 * This class was originally copied from the Symfony Framework:
 * Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * Adjusted in very many places
 *
 * @package redaxo\core
 */
class rex_autoload
{
    // see https://github.com/redaxo/redaxo/issues/5780
    private const SYMFONY_NON_UTF8_CLASS = "\xA9";

    private const SYMFONY_NON_UTF8_CLASS_REPLACEMENT = 'rexsymfonycachevaluewrappernonutf8class';

    /** @var ClassLoader */
    protected static $composerLoader;

    /** @var bool */
    protected static $registered = false;
    /** @var string|null */
    protected static $cacheFile;
    /** @var bool */
    protected static $cacheChanged = false;
    /** @var bool remember the cache was deleted, to make sure we don't generate a stale cache file */
    protected static $cacheDeleted = false;
    /** @var bool */
    protected static $reloaded = false;
    /** @var array<string, array<string, string>> */
    protected static $dirs = [];
    /** @var list<string> */
    protected static $addedDirs = [];
    /** @var array<string, string> */
    protected static $classes = [];

    /**
     * Register rex_autoload in spl autoloader.
     * @return void
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        ini_set('unserialize_callback_func', 'spl_autoload_call');

        if (!self::$composerLoader) {
            self::$composerLoader = require rex_path::core('vendor/autoload.php');
            // Unregister Composer Autoloader because we call self::$composerLoader->loadClass() manually
            self::$composerLoader->unregister();
        }

        if (!spl_autoload_register(self::autoload(...))) {
            throw new Exception(sprintf('Unable to register %s::autoload as an autoloading method.', self::class));
        }

        self::$cacheFile = rex_path::coreCache('autoload.cache');
        self::loadCache();
        register_shutdown_function([self::class, 'saveCache']);

        self::$registered = true;
    }

    /**
     * Unregister rex_autoload from spl autoloader.
     * @return void
     */
    public static function unregister()
    {
        spl_autoload_unregister(self::autoload(...));
        self::$registered = false;
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name
     *
     * @return bool Returns true if the class has been loaded
     */
    public static function autoload($class)
    {
        // class already exists
        if (self::classExists($class)) {
            return true;
        }

        $force = false;

        if (self::SYMFONY_NON_UTF8_CLASS === $class) {
            $lowerClass = self::SYMFONY_NON_UTF8_CLASS_REPLACEMENT;
        } else {
            $lowerClass = strtolower($class);
        }

        if (isset(self::$classes[$lowerClass])) {
            $path = rex_path::base(self::$classes[$lowerClass]);
            // we have a class path for the class, let's include it
            if (@include_once $path) {
                if (self::classExists($class)) {
                    return true;
                }
            }
            // there is a class path in cache, but the file does not exist or does not contain the class any more
            // but maybe the class exists in another already known file now
            // so all files have to be analysed again => $force reload
            $force = true;
            unset(self::$classes[$lowerClass]);
            self::$cacheChanged = true;
        }

        // Return true if class exists after calling $composerLoader
        if (self::$composerLoader->loadClass($class) && self::classExists($class)) {
            return true;
        }

        // Class not found, so reanalyse all directories if not already done or if $force==true
        // but only if an admin is logged in
        if (
            (!self::$reloaded || $force)
            && (rex::isSetup() || rex::getConsole() || rex::isDebugMode() || ($user = rex_backend_login::createUser()) && $user->isAdmin())
        ) {
            self::reload($force);
            return self::autoload($class);
        }

        return false;
    }

    /**
     * Returns whether the given class/interface/trait exists.
     *
     * @param string $class
     *
     * @return bool
     *
     * @phpstan-impure
     */
    private static function classExists($class)
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
    }

    /**
     * Loads the cache.
     * @return void
     */
    private static function loadCache()
    {
        if (!self::$cacheFile || !($cache = @file_get_contents(self::$cacheFile))) {
            return;
        }

        [self::$classes, self::$dirs] = json_decode($cache, true);
    }

    /**
     * Saves the cache.
     * @return void
     */
    public static function saveCache()
    {
        if (!self::$cacheChanged || self::$cacheDeleted) {
            return;
        }

        // dont persist a possible incomplete cache, because requests of end-users (which are not allowed to regenerate a existing cache)
        // can error in some crazy class-not-found errors which are hard to debug.
        $error = error_get_last();
        if (is_array($error) && in_array($error['type'], [E_USER_ERROR, E_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
            return;
        }

        // remove obsolete dirs from cache
        foreach (self::$dirs as $dir => $files) {
            if (!in_array($dir, self::$addedDirs)) {
                unset(self::$dirs[$dir]);
            }
        }

        if (!rex_file::putCache(self::$cacheFile, [self::$classes, self::$dirs])) {
            throw new Exception("Unable to write autoload cachefile '" . self::$cacheFile . "'!");
        }
        self::$cacheChanged = false;
    }

    /**
     * Reanalyses all added directories.
     *
     * @param bool $force If true, all files are reanalysed, otherwise only new and changed files
     * @return void
     */
    public static function reload($force = false)
    {
        if ($force) {
            self::$classes = [];
            self::$dirs = [];
        }
        foreach (self::$addedDirs as $dir) {
            self::_addDirectory($dir);
        }
        self::$reloaded = true;
    }

    /**
     * Removes the cache.
     * @return void
     */
    public static function removeCache()
    {
        rex_file::delete(self::$cacheFile);
        self::$cacheDeleted = true;
    }

    /**
     * Adds a directory to the autoloading system if not yet present.
     *
     * @param string $dir The directory to look for classes
     * @return void
     */
    public static function addDirectory($dir)
    {
        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        $dir = rex_path::relative($dir);
        if (in_array($dir, self::$addedDirs)) {
            return;
        }
        self::$addedDirs[] = $dir;
        if (!isset(self::$dirs[$dir])) {
            self::_addDirectory($dir);
        }
    }

    /**
     * Returns the classes.
     *
     * @return list<string>
     */
    public static function getClasses()
    {
        return array_keys(self::$classes);
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function _addDirectory($dir)
    {
        $dirPath = rex_path::base($dir);

        if (!is_dir($dirPath)) {
            return;
        }

        if (!isset(self::$dirs[$dir])) {
            self::$dirs[$dir] = [];
            self::$cacheChanged = true;
        }
        $files = self::$dirs[$dir];
        $composerClassMap = self::$composerLoader->getClassMap();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $path => $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'inc'])) {
                continue;
            }

            $file = rex_path::relative($path);
            unset($files[$file]);
            $checksum = (string) filemtime($path);
            if (!$checksum || isset(self::$dirs[$dir][$file]) && self::$dirs[$dir][$file] === $checksum) {
                continue;
            }
            self::$dirs[$dir][$file] = $checksum;
            self::$cacheChanged = true;

            $classes = self::findClasses($path);
            foreach ($classes as $class) {
                // Force usage of core vendors (via composer autoloader)
                if (isset($composerClassMap[$class])) {
                    continue;
                }

                if (self::SYMFONY_NON_UTF8_CLASS === $class) {
                    $class = self::SYMFONY_NON_UTF8_CLASS_REPLACEMENT;
                } else {
                    $class = strtolower($class);
                }

                if (!isset(self::$classes[$class])) {
                    self::$classes[$class] = $file;
                }
            }
        }
        foreach ($files as $file) {
            unset(self::$dirs[$file]);
            self::$cacheChanged = true;
        }
    }

    /**
     * Extract the classes in the given file.
     *
     * The method is copied from Composer (with little changes):
     * https://github.com/composer/composer/blob/d99b200cf3b9d24a166141420ca28c6a99f27bf5/src/Composer/Autoload/ClassMapGenerator.php#L215
     *
     * @param string $path The file to check
     *
     * @throws RuntimeException
     *
     * @return list<string> The found classes
     */
    private static function findClasses($path)
    {
        $extraTypes = PHP_VERSION_ID < 50400 ? '' : '|trait';
        if (PHP_VERSION_ID >= 80100 || (defined('HHVM_VERSION') && version_compare(HHVM_VERSION, '3.3', '>='))) {
            $extraTypes .= '|enum';
        }

        /**
         * Use @ here instead of Silencer to actively suppress 'unhelpful' output.
         * @see https://github.com/composer/composer/pull/4886
         */
        $contents = @php_strip_whitespace($path);
        if (!$contents) {
            if (!is_file($path)) {
                $message = 'File at "%s" does not exist, check your classmap definitions';
            } elseif (!is_readable($path)) {
                $message = 'File at "%s" is not readable, check its permissions';
            } elseif ('' === trim(file_get_contents($path))) {
                // The input file was really empty and thus contains no classes
                return [];
            } else {
                $message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
            }
            $error = error_get_last();
            if (isset($error['message'])) {
                $message .= PHP_EOL . 'The following message may be helpful:' . PHP_EOL . $error['message'];
            }
            throw new RuntimeException(sprintf($message, $path));
        }

        // return early if there is no chance of matching anything in this file
        if (!preg_match('{\b(?:class|interface' . $extraTypes . ')\s}i', $contents)) {
            return [];
        }

        // strip heredocs/nowdocs
        $heredocRegex = '{
            # opening heredoc/nowdoc delimiter (word-chars)
            <<<[ \t]*+([\'"]?)(\w++)\\1
            # needs to be followed by a newline
            (?:\r\n|\n|\r)
            # the meat of it, matching line by line until end delimiter
            (?:
                # a valid line is optional white-space (possessive match) not followed by the end delimiter, then anything goes for the rest of the line
                [\t ]*+(?!\\2 \b)[^\r\n]*+
                # end of line(s)
                [\r\n]++
            )*
            # end delimiter
            [\t ]*+ \\2 (?=\b)
        }x';

        // run first assuming the file is valid unicode
        $contentWithoutHeredoc = preg_replace($heredocRegex . 'u', 'null', $contents);
        if (null === $contentWithoutHeredoc) {
            // run again without unicode support if the file failed to be parsed
            $contents = preg_replace($heredocRegex, 'null', $contents);
        } else {
            $contents = $contentWithoutHeredoc;
        }
        unset($contentWithoutHeredoc);

        // strip strings
        $contents = preg_replace('{"[^"\\\\]*+(\\\\.[^"\\\\]*+)*+"|\'[^\'\\\\]*+(\\\\.[^\'\\\\]*+)*+\'}s', 'null', $contents);
        // strip leading non-php code if needed
        if (!str_starts_with($contents, '<?')) {
            $contents = preg_replace('{^.+?<\?}s', '<?', $contents, 1, $replacements);
            if (0 === $replacements) {
                return [];
            }
        }
        // strip non-php blocks in the file
        $contents = preg_replace('{\?>(?:[^<]++|<(?!\?))*+<\?}s', '?><?', $contents);
        // strip trailing non-php code if needed
        $pos = strrpos($contents, '?>');
        if (false !== $pos && !str_contains(substr($contents, $pos), '<?')) {
            $contents = substr($contents, 0, $pos);
        }
        // strip comments if short open tags are in the file
        if (preg_match('{(<\?)(?!(php|hh))}i', $contents)) {
            $contents = preg_replace('{//.* | /\*(?:[^*]++|\*(?!/))*\*/}x', '', $contents);
        }

        preg_match_all('{
            (?:
                 \b(?<![\$:>])(?P<type>class|interface' . $extraTypes . ') \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
               | \b(?<![\$:>])(?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
            )
        }ix', $contents, $matches);

        $classes = [];
        $namespace = '';

        for ($i = 0, $len = count($matches['type']); $i < $len; ++$i) {
            if (!empty($matches['ns'][$i])) {
                $namespace = str_replace([' ', "\t", "\r", "\n"], '', $matches['nsname'][$i]) . '\\';
            } else {
                $name = $matches['name'][$i];
                // skip anon classes extending/implementing
                if ('extends' === $name || 'implements' === $name) {
                    continue;
                }
                if (':' === $name[0]) {
                    // This is an XHP class, https://github.com/facebook/xhp
                    $name = 'xhp' . substr(str_replace(['-', ':'], ['_', '__'], $name), 1);
                } elseif ('enum' === $matches['type'][$i]) {
                    // In Hack, something like:
                    //   enum Foo: int { HERP = '123'; }
                    // The regex above captures the colon, which isn't part of
                    // the class name.
                    $name = rtrim($name, ':');
                }
                $classes[] = ltrim($namespace . $name, '\\');
            }
        }

        return $classes;
    }
}
