<?php

/**
 * Deletes the cache.
 *
 * @package redaxo\core
 *
 * @return string
 */
function rex_delete_cache()
{
    // close logger, so the logfile can also be deleted
    rex_logger::close();

    $finder = rex_finder::factory(rex_path::cache())
        ->recursive()
        ->childFirst()
        ->ignoreFiles(['.htaccess', '.redaxo'], false)
        ->ignoreSystemStuff(false)
        ->ignoreUnreadableDirs();
    rex_dir::deleteIterator($finder);

    rex_autoload::removeCache();
    rex_clang::reset();

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    // ----- EXTENSION POINT
    return rex_extension::registerPoint(new rex_extension_point('CACHE_DELETED', rex_i18n::msg('delete_cache_message')));
}

/**
 * @param string $varname
 *
 * @return int
 *
 * @package redaxo\core
 */
function rex_ini_get($varname)
{
    $val = trim(ini_get($varname));
    if ('' != $val) {
        $last = strtolower($val[strlen($val) - 1]);
    } else {
        $last = '';
    }
    $val = (int) $val;
    switch ($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
            // no break
        case 'm':
            $val *= 1024;
            // no break
        case 'k':
            $val *= 1024;
    }

    return $val;
}
