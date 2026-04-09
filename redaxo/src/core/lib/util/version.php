<?php

/**
 * @author staabm
 *
 * @package redaxo\core
 */
class rex_version
{
    private function __construct()
    {
        // noop
    }

    public static function isUnstable(string $version): bool
    {
        // see https://www.php.net/manual/en/function.version-compare.php
        return (bool) preg_match('/(?<![a-z])(?:dev|alpha|a|beta|b|rc|pl)(?![a-z])/i', $version);
    }

    /**
     * Checks the version of the requirement.
     *
     * @param string $version Version
     * @param string $constraints Constraint list, separated by comma
     *
     * @throws rex_exception
     */
    public static function matchesConstraints(string $version, string $constraints): bool
    {
        $rawConstraints = array_filter(array_map('trim', explode(',', $constraints)));
        $constraints = [];
        foreach ($rawConstraints as $constraint) {
            if ('*' === $constraint) {
                continue;
            }

            if (!preg_match('/^(?<op>==?|<=?|>=?|!=|~|\^|) ?(?<version>\d+(?:\.\d+)*)(?<wildcard>\.\*)?(?<prerelease>[ -.]?[a-z]+(?:[ -.]?\d+)?)?$/i', $constraint, $match)
                || isset($match['wildcard']) && $match['wildcard'] && ('' != $match['op'] || isset($match['prerelease']) && $match['prerelease'])
            ) {
                throw new rex_exception('Unknown version constraint "' . $constraint . '"!');
            }

            if (isset($match['wildcard']) && $match['wildcard']) {
                $constraints[] = ['>=', $match['version']];
                $pos = strrpos($match['version'], '.');
                if (false === $pos) {
                    $constraints[] = ['<', (int) $match['version'] + 1];
                } else {
                    ++$pos;
                    $sub = (int) substr($match['version'], $pos);
                    $constraints[] = ['<', substr_replace($match['version'], (string) ($sub + 1), $pos)];
                }
            } elseif (in_array($match['op'], ['~', '^'])) {
                $constraints[] = ['>=', $match['version'] . ($match['prerelease'] ?? '')];
                if ('^' === $match['op'] || false === $pos = strrpos($match['version'], '.')) {
                    // add "-foo" to get a version lower than a "-dev" version
                    $constraints[] = ['<', ((int) $match['version'] + 1) . '-foo'];
                } else {
                    $main = '';
                    $sub = substr($match['version'], 0, $pos);
                    if (false !== ($pos = strrpos($sub, '.'))) {
                        $main = substr($sub, 0, $pos + 1);
                        $sub = substr($sub, $pos + 1);
                    }
                    // add "-foo" to get a version lower than a "-dev" version
                    $constraints[] = ['<', $main . ((int) $sub + 1) . '-foo'];
                }
            } else {
                $constraints[] = [$match['op'] ?: '=', $match['version'] . ($match['prerelease'] ?? '')];
            }
        }

        /** @var array{0: '='|'=='|'!='|'<>'|'<'|'<='|'>'|'>=', 1: string} $constraint */
        foreach ($constraints as $constraint) {
            if (!self::compare($version, $constraint[1], $constraint[0])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Splits a version string into its parts.
     *
     * @return list<string>
     */
    public static function split(string $version): array
    {
        return preg_split('/(?<=\d)(?=[a-z])|(?<=[a-z])(?=\d)|[ ._-]+/i', $version);
    }

    /**
     * Compares two version number strings.
     *
     * In contrast to version_compare() it treats "1.0" and "1.0.0" as equal and it supports a space as separator for
     * the version parts, e.g. "1.0 beta1"
     *
     * @see https://www.php.net/manual/en/function.version-compare.php
     *
     * @param string $version1 First version number
     * @param string $version2 Second version number
     * @param '='|'=='|'!='|'<>'|'<'|'<='|'>'|'>='|null $comparator Optional comparator
     *
     * @return bool
     */
    public static function compare(string $version1, string $version2, ?string $comparator = '<')
    {
        // bc
        $comparator ??= '<';

        if (!in_array($comparator, ['=', '==', '!=', '<>', '<', '<=', '>', '>='], true)) {
            throw new InvalidArgumentException(sprintf('Unknown comparator "%s".', $comparator));
        }

        $version1 = self::split($version1);
        $version2 = self::split($version2);
        $max = max(count($version1), count($version2));
        $version1 = implode('.', array_pad($version1, $max, '0'));
        $version2 = implode('.', array_pad($version2, $max, '0'));

        return version_compare($version1, $version2, $comparator);
    }

    /**
     * Returns the current git version hash for the given path.
     *
     * @param string $path A local filesystem path
     * @param string|null $repo If given, the version hash is returned only if the remote repository matches the
     *                          given github repo (e.g. `redaxo/core`)
     */
    public static function gitHash($path, ?string $repo = null): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $exitCode = -1;

        if (null !== $repo) {
            $command = 'git -C ' . escapeshellarg($path) . ' ls-remote --get-url';
            $remote = @exec($command, $output, $exitCode);

            if (0 !== $exitCode || !preg_match('{github.com[:/]' . preg_quote($repo) . '\.git$}i', $remote)) {
                return null;
            }
        }

        $command = 'git -C ' . escapeshellarg($path) . ' log -1 --pretty=format:%h';
        $version = @exec($command, $output, $exitCode);

        return 0 === $exitCode ? $version : null;
    }
}
