<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Format;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\FormatNegotiator;

/** @internal */
final class FormatNegotiatorTest extends TestCase
{
    public function testPicksFirstAcceptedCandidateByPreference(): void
    {
        $candidates = [Format::AVIF, Format::WEBP];

        // client accepts both -> first candidate (AVIF) wins
        self::assertSame(Format::AVIF, FormatNegotiator::negotiate($candidates, 'image/avif,image/webp,image/*'));
        // only webp accepted
        self::assertSame(Format::WEBP, FormatNegotiator::negotiate($candidates, 'image/webp,image/png'));
    }

    public function testReturnsNullWhenNoCandidateAccepted(): void
    {
        self::assertNull(FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], 'text/html,image/png'));
        self::assertNull(FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], ''));
    }

    public function testRespectsCandidatePreferenceOrder(): void
    {
        // when only the second candidate is encodable, the caller passes just that one
        self::assertSame(Format::WEBP, FormatNegotiator::negotiate([Format::WEBP], 'image/avif,image/webp'));
        // an empty candidate list never negotiates
        self::assertNull(FormatNegotiator::negotiate([], 'image/avif,image/webp'));
    }
}
