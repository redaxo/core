<?php

namespace Redaxo\Core\Tests\RexVar;

use PHPUnit\Framework\Attributes\DataProvider;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;

/**
 * @internal
 */
final class ConfigVarTest extends RexVarTestBase
{
    protected function setUp(): void
    {
        Core::setConfig('myCoreConfig', 'myCoreConfigValue');
        Addon::get('test')->setConfig('myPackageConfig', 'myPackageConfigValue');
    }

    protected function tearDown(): void
    {
        Core::removeConfig('myCoreConfig');
        Addon::get('test')->removeConfig('tests');
    }

    /** @return list<array{string, string}> */
    public static function configReplaceProvider(): array
    {
        return [
            ['REX_CONFIG[key=myCoreConfig]', 'myCoreConfigValue'],
            ['REX_CONFIG[namespace=test key=myPackageConfig]', 'myPackageConfigValue'],
        ];
    }

    #[DataProvider('configReplaceProvider')]
    public function testConfigReplace(string $content, string $expectedOutput): void
    {
        $this->assertParseOutputEquals($expectedOutput, $content);
    }
}
