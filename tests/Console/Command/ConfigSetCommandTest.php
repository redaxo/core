<?php

namespace Redaxo\Core\Tests\Console\Command;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Console\Command\ConfigSetCommand;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Tester\CommandTester;

/** @internal */
final class ConfigSetCommandTest extends TestCase
{
    private string $initialConfig;

    #[Override]
    protected function setUp(): void
    {
        $configPath = Path::coreData('config.yml');
        $this->initialConfig = file_get_contents($configPath);
    }

    #[Override]
    protected function tearDown(): void
    {
        $configPath = Path::coreData('config.yml');
        file_put_contents($configPath, $this->initialConfig);
    }

    #[DataProvider('dataSetBoolean')]
    public function testSetBoolean(bool $expectedValue, string $value): void
    {
        $commandTester = new CommandTester(new ConfigSetCommand());
        $commandTester->execute([
            '--type' => 'bool',
            'key' => 'test',
            'value' => $value,
        ]);
        $config = File::getConfig(Path::coreData('config.yml'));
        self::assertArrayHasKey('test', $config);
        self::assertIsBool($config['test']);
        self::assertEquals($expectedValue, $config['test']);
        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /** @return list<array{bool, string}> */
    public static function dataSetBoolean(): array
    {
        return [
            [true, '1'],
            [false, '0'],
            [true, 'true'],
            [false, 'false'],
            [true, 'on'],
            [false, 'off'],
        ];
    }
}
