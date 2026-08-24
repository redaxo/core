<?php

namespace Redaxo\Core;

use Redaxo\Core\Exception\LogicException;

use function is_string;
use function sprintf;

use const FILTER_VALIDATE_BOOL;

/**
 * Access to environment variables (usually defined in the `.env` file).
 *
 * The variables are read from `$_SERVER` and `$_ENV`, in that order.
 */
final class Env
{
    private function __construct() {}

    /**
     * Returns the value of the env var, or `null` if it is not set or empty.
     *
     * @return non-empty-string|null
     * @phpstan-impure
     */
    public static function get(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * Returns the value of the env var and throws if it is not set or empty.
     *
     * @return non-empty-string
     * @phpstan-impure
     */
    public static function require(string $name): string
    {
        return self::get($name) ?? throw new LogicException(sprintf('The env var "%s" is missing, it must be defined in the ".env" file.', $name));
    }

    /**
     * Returns the value of the env var interpreted as boolean, e.g. `1`, `true`, `on` and `yes` are considered `true`.
     *
     * @phpstan-impure
     */
    public static function getBool(string $name): bool
    {
        return filter_var(self::get($name), FILTER_VALIDATE_BOOL);
    }
}
