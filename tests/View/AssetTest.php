<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\View\Asset;

/** @internal */
final class AssetTest extends TestCase
{
    protected function setUp(): void
    {
        Asset::reset();
    }

    protected function tearDown(): void
    {
        Asset::reset();
    }

    public function testAddGetCss(): void
    {
        Asset::addCssFile('my.css');
        $files = Asset::getCssFiles()['all'];
        self::assertTrue('my.css' == end($files));

        Asset::addCssFile('print.css', 'print');
        $files = Asset::getCssFiles()['print'];
        self::assertTrue('print.css' == end($files));
    }

    public function testAddGetJs(): void
    {
        Asset::addJsFile('my.js');
        $files = Asset::getJsFiles();
        self::assertTrue('my.js' == end($files));
    }

    public function testAddGetJsWithOptions(): void
    {
        Asset::addJsFile('my.js');
        $files = Asset::getJsFilesWithOptions();
        [$file, $options] = end($files);
        self::assertTrue('my.js' == $file);
        self::assertTrue($options == [Asset::JS_IMMUTABLE => false], 'options default to JS_IMMUTABLE=false');

        Asset::addJsFile('my.js', [Asset::JS_IMMUTABLE => true]);
        $files = Asset::getJsFilesWithOptions();
        [$file, $options] = end($files);
        self::assertTrue('my.js' == $file);
        self::assertTrue($options == [Asset::JS_IMMUTABLE => true], 'explicit JS_IMMUTABLE option');

        Asset::addJsFile('my_async.js', [Asset::JS_ASYNC => true, Asset::JS_DEFERED => true]);
        $files = Asset::getJsFilesWithOptions();
        [$file, $options] = end($files);
        self::assertTrue('my_async.js' == $file);
        self::assertTrue($options == [Asset::JS_ASYNC => true, Asset::JS_DEFERED => true], 'multiple options');
    }

    /** @psalm-suppress DeprecatedMethod the deprecated method is tested */
    public function testRequireBootstrapCompatAfterTheHeadThrows(): void
    {
        Asset::requireBootstrapCompat(); // @phpstan-ignore staticMethod.deprecated
        Asset::getCssFiles();
        Asset::requireBootstrapCompat(); // @phpstan-ignore staticMethod.deprecated (already added)

        Asset::reset();
        Asset::getCssFiles();

        $this->expectException(LogicException::class);
        Asset::requireBootstrapCompat(); // @phpstan-ignore staticMethod.deprecated
    }

    /** @psalm-suppress DeprecatedMethod the deprecated method is tested */
    public function testResetForgetsTheReadHead(): void
    {
        Asset::getCssFiles();
        Asset::reset();

        Asset::requireBootstrapCompat(); // @phpstan-ignore staticMethod.deprecated

        self::assertCount(1, Asset::getCssFiles()['all']);
        self::assertSame([], Asset::getJsFiles());
    }
}
