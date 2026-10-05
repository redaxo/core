<?php

namespace Redaxo\ComposerPlugin\Tests;

use PHPUnit\Framework\TestCase;
use Redaxo\ComposerPlugin\ProjectInitializer;

use function dirname;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class ProjectInitializerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/redaxo-composer-plugin-' . bin2hex(random_bytes(4)) . '/My Site';
        mkdir($this->dir, recursive: true);

        $skeleton = dirname(__DIR__, 2) . '/project';
        copy($skeleton . '/composer.json', $this->dir . '/composer.json');
        copy($skeleton . '/.env', $this->dir . '/.env');
    }

    protected function tearDown(): void
    {
        foreach (['composer.json', '.env', 'README.md'] as $file) {
            @unlink($this->dir . '/' . $file);
        }
        rmdir($this->dir);
        rmdir(dirname($this->dir));
    }

    public function testInitialize(): void
    {
        new ProjectInitializer($this->dir)->initialize();

        /** @var array<string, mixed> $composerJson */
        $composerJson = json_decode((string) file_get_contents($this->dir . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['name', 'description', 'keywords', 'homepage', 'authors', 'support'] as $key) {
            self::assertArrayNotHasKey($key, $composerJson);
        }
        self::assertSame('proprietary', $composerJson['license']);
        self::assertArrayHasKey('require', $composerJson);

        $env = (string) file_get_contents($this->dir . '/.env');
        self::assertMatchesRegularExpression('/^REX_INSTANCE_ID=my-site-[0-9a-f]{8}$/m', $env);
        self::assertStringContainsString("\nREX_INSTANCE_NAME='My Site'\n", $env);

        self::assertStringStartsWith("# My Site\n", (string) file_get_contents($this->dir . '/README.md'));
    }
}
