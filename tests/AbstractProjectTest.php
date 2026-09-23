<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\AbstractProject;
use Redaxo\Core\Env;
use Redaxo\Core\Environment;
use Redaxo\Core\Exception\InvalidArgumentException;

/** @internal */
final class AbstractProjectTest extends TestCase
{
    public function testInstanceName(): void
    {
        $origServer = Env::get('REX_INSTANCE_NAME');
        $origEnv = $_ENV['REX_INSTANCE_NAME'] ?? null;

        try {
            $project = new class(Environment::Frontend) extends AbstractProject {};

            $_SERVER['REX_INSTANCE_NAME'] = 'My Site';
            self::assertSame('My Site', $project->instanceName);

            unset($_SERVER['REX_INSTANCE_NAME'], $_ENV['REX_INSTANCE_NAME']);
            self::assertSame('REDAXO', $project->instanceName);

            $project = new class(Environment::Frontend) extends AbstractProject {
                public string $instanceName = 'Configured';
            };
            $_SERVER['REX_INSTANCE_NAME'] = 'My Site';
            self::assertSame('Configured', $project->instanceName);
        } finally {
            if (null === $origServer) {
                unset($_SERVER['REX_INSTANCE_NAME']);
            } else {
                $_SERVER['REX_INSTANCE_NAME'] = $origServer;
            }
            if (null !== $origEnv) {
                $_ENV['REX_INSTANCE_NAME'] = $origEnv;
            }
        }
    }

    #[DataProvider('provideBaseUrl')]
    public function testBaseUrl(string $expected, string $url): void
    {
        $project = new class(Environment::Frontend) extends AbstractProject {};
        $project->baseUrl = $url;

        self::assertSame($expected, $project->baseUrl);
    }

    /** @return list<array{string, string}> */
    public static function provideBaseUrl(): array
    {
        return [
            ['https://example.org/', 'https://example.org'],
            ['https://example.org/', 'https://example.org/'],
            ['https://example.org/sub/', 'https://example.org/sub'],
        ];
    }

    public function testBaseUrlWithInvalidValue(): void
    {
        $project = new class(Environment::Frontend) extends AbstractProject {};

        $this->expectException(InvalidArgumentException::class);
        $project->baseUrl = 'example.org';
    }

    public function testBaseUrlFromEnv(): void
    {
        $orig = Env::get('REX_BASE_URL');

        try {
            $_SERVER['REX_BASE_URL'] = 'https://example.org';
            $project = new class(Environment::Frontend) extends AbstractProject {};
            self::assertSame('https://example.org/', $project->baseUrl);

            $_SERVER['REX_BASE_URL'] = 'example.org';
            $project = new class(Environment::Frontend) extends AbstractProject {};
            $this->expectException(InvalidArgumentException::class);
            self::assertNull($project->baseUrl);
        } finally {
            if (null === $orig) {
                unset($_SERVER['REX_BASE_URL']);
            } else {
                $_SERVER['REX_BASE_URL'] = $orig;
            }
        }
    }
}
