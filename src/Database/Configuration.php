<?php

namespace Redaxo\Core\Database;

final readonly class Configuration
{
    public string $host;
    public string $login;
    public string $password;
    public string $name;
    public bool $persistent;

    public ?string $sslKey;
    public ?string $sslCert;
    public string|bool|null $sslCa;
    public bool $sslVerifyServerCert;

    /**
     * @param array<string, string|bool> $dbConfig
     * @psalm-suppress PossiblyInvalidPropertyAssignmentValue
     */
    public function __construct(array $dbConfig)
    {
        $this->host = $dbConfig['host'];
        $this->login = $dbConfig['login'];
        $this->password = $dbConfig['password'];
        $this->name = $dbConfig['name'];
        $this->persistent = $dbConfig['persistent'] ?? false;

        $this->sslKey = $dbConfig['ssl_key'] ?? null;
        $this->sslCert = $dbConfig['ssl_cert'] ?? null;
        $this->sslCa = $dbConfig['ssl_ca'] ?? null;
        $this->sslVerifyServerCert = $dbConfig['ssl_verify_server_cert'] ?? true;
    }
}
