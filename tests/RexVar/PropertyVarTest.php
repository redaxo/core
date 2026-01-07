<?php

namespace Redaxo\Core\Tests\RexVar;

use PHPUnit\Framework\Attributes\DataProvider;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;

/**
 * @internal
 */
final class PropertyVarTest extends RexVarTestBase
{
    protected function setUp(): void
    {
        Core::setProperty('myCoreProperty', 'myCorePropertyValue');
        Addon::get('test')->setProperty('myPackageProperty', 'myPackagePropertyValue');
    }

    protected function tearDown(): void
    {
        Core::removeProperty('myCoreProperty');
        Addon::get('test')->removeProperty('tests');
    }

    /** @return list<array{string, string}> */
    public static function propertyReplaceProvider(): array
    {
        return [
            ['REX_PROPERTY[key=myCoreProperty]', 'myCorePropertyValue'],
            ['REX_PROPERTY[namespace=test key=myPackageProperty]', 'myPackagePropertyValue'],
        ];
    }

    #[DataProvider('propertyReplaceProvider')]
    public function testPropertyReplace(string $content, string $expectedOutput): void
    {
        $this->assertParseOutputEquals($expectedOutput, $content);
    }
}
