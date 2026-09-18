<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;

use function filter_var;
use function is_string;
use function ltrim;
use function parse_str;
use function parse_url;
use function preg_replace;
use function rawurldecode;
use function sprintf;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

final readonly class ConnectionConfig
{
    public function __construct(
        /** Host name, optionally including a port as `host:port`. */
        public string $host,
        public string $login,
        public string $password,
        public string $name,
        public bool $persistent = false,
        public ?string $sslKey = null,
        public ?string $sslCert = null,
        public string|bool|null $sslCa = null,
        public bool $sslVerifyServerCert = true,
    ) {}

    /**
     * Returns the configuration of the given database connection.
     *
     * @param positive-int $db
     */
    public static function get(int $db = 1): self
    {
        return self::fromUrl(Env::require(self::getEnvVar($db)));
    }

    /**
     * Returns the configuration of every database connection, keyed by its number.
     *
     * The connections must be numbered without gaps, the first undefined one ends the list.
     *
     * @return array<positive-int, self>
     */
    public static function getAll(): array
    {
        $configs = [];

        for ($db = 1; null !== Env::get(self::getEnvVar($db)); ++$db) {
            $configs[$db] = self::get($db);
        }

        return $configs;
    }

    /**
     * The first connection uses the widely established `DATABASE_URL`, which several hosting platforms set
     * on their own when a database is provisioned. Further connections are numbered.
     *
     * @param positive-int $db
     */
    private static function getEnvVar(int $db): string
    {
        return 1 === $db ? 'DATABASE_URL' : 'DATABASE_URL_' . $db;
    }

    /**
     * Creates the configuration from a database url of the form `mysql://<login>:<password>@<host>:<port>/<name>`.
     *
     * Everything beyond host, credentials and database name is passed as query parameters: `persistent`,
     * `ssl_key`, `ssl_cert`, `ssl_ca` and `ssl_verify_server_cert`. Unknown parameters are ignored, so a url
     * carrying parameters of another framework (`serverVersion`, `charset`) can be used as it is.
     *
     * Characters that are special in a url must be percent-encoded in the credentials. `@` and `:` happen to
     * survive unencoded, but `/`, `?` and `#` do not and make the url unparsable.
     */
    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException(sprintf(
                'Malformed database url "%s". Expected the form "mysql://<login>:<password>@<host>:<port>/<name>". '
                . 'If the login or password contains "/", "?" or "#", those characters must be percent-encoded.',
                self::redact($url),
            ));
        }

        if ('mysql' !== $parts['scheme']) {
            throw new InvalidArgumentException(sprintf('Unsupported database url scheme "%s", expected "mysql".', $parts['scheme']));
        }

        $name = ltrim($parts['path'] ?? '', '/');

        if ('' === $name) {
            throw new InvalidArgumentException(sprintf('Database url "%s" does not contain a database name.', self::redact($url)));
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return new self(
            host: $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''),
            login: rawurldecode($parts['user'] ?? ''),
            password: rawurldecode($parts['pass'] ?? ''),
            name: rawurldecode($name),
            persistent: self::bool($query['persistent'] ?? null) ?? false,
            sslKey: self::string($query['ssl_key'] ?? null),
            sslCert: self::string($query['ssl_cert'] ?? null),
            // either a path to a ca file, or a flag to use the system ca store
            sslCa: self::string($query['ssl_ca'] ?? null) ?? self::bool($query['ssl_ca'] ?? null),
            sslVerifyServerCert: self::bool($query['ssl_verify_server_cert'] ?? null) ?? true,
        );
    }

    /** Like {@see fromUrl()}, but returns `null` instead of throwing when the url is missing or malformed. */
    public static function tryFromUrl(?string $url): ?self
    {
        if (null === $url) {
            return null;
        }

        try {
            return self::fromUrl($url);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Hides the credentials, so that the url can be part of an error message.
     *
     * Deliberately greedy up to the last `@`: the urls that end up in an error message are the malformed ones,
     * where the credentials cannot be delimited reliably. Redacting too much is harmless, too little is not.
     */
    private static function redact(string $url): string
    {
        return (string) preg_replace('{^(\w+://).*@}', '$1***@', $url);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && '' !== $value && null === self::bool($value) ? $value : null;
    }

    private static function bool(mixed $value): ?bool
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
