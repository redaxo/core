<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Format;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\MediaQuality;

/** @internal */
final class MediaQualityTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame(80, MediaQuality::get(Format::JPEG));
        self::assertSame(85, MediaQuality::get(Format::WEBP));
        self::assertSame(60, MediaQuality::get(Format::AVIF));

        // lossless formats have no quality
        self::assertNull(MediaQuality::get(Format::PNG));
        self::assertNull(MediaQuality::get(Format::GIF));
    }
}
