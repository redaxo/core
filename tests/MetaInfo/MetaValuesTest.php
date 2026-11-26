<?php

namespace Redaxo\Core\Tests\MetaInfo;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaValues;
use TypeError;

/**
 * Uses the fixture `TestMediaMetaSchema`, as only discovered schemas belong to an entity.
 * @internal
 */
final class MetaValuesTest extends TestCase
{
    public function testForTable(): void
    {
        $values = new MetaValues(MetaEntity::Media, ['ai_generated' => true, 'alt_text' => 'Alt text']);

        self::assertSame(['meta_ai_generated' => 1], $values->forTable('rex_media'));
        self::assertSame(['meta_alt_text' => 'Alt text'], $values->forTable('rex_media_translation'));
    }

    public function testOnlyGivenValues(): void
    {
        $values = new MetaValues(MetaEntity::Media, ['alt_text' => 'Alt text']);

        self::assertSame([], $values->forTable('rex_media'));
        self::assertSame(['meta_alt_text' => 'Alt text'], $values->forTable('rex_media_translation'));
    }

    public function testUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown meta field "aiGenerated" of Media');

        new MetaValues(MetaEntity::Media, ['aiGenerated' => true]);
    }

    public function testInvalidType(): void
    {
        $this->expectException(TypeError::class);

        new MetaValues(MetaEntity::Media, ['alt_text' => ['no', 'string']]);
    }
}
