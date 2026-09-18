<?php

namespace Redaxo\Core\Tests\MediaPool;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaPool\Media;
use ReflectionClass;

/** @internal */
final class MediaTest extends TestCase
{
    public function testHasValue(): void
    {
        $media = $this->createMediaWithoutConstructor();

        self::assertTrue($media->hasValue('meta_foo'));
        self::assertTrue($media->hasValue('foo'));

        self::assertFalse($media->hasValue('bar'));
        self::assertFalse($media->hasValue('meta_bar'));
    }

    public function testGetValue(): void
    {
        $media = $this->createMediaWithoutConstructor();

        self::assertEquals('teststring', $media->getValue('meta_foo'));
        self::assertEquals('teststring', $media->getValue('foo'));

        self::assertNull($media->getValue('bar'));
        self::assertNull($media->getValue('meta_bar'));
    }

    private function createMediaWithoutConstructor(): Media
    {
        $reflectionClass = new ReflectionClass(Media::class);
        $media = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('additionalData')->setValue($media, ['meta_foo' => 'teststring']);

        return $media;
    }
}
