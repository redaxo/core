<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\DriverInterface;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\FormatNegotiator;

use function in_array;

/** @internal */
final class FormatNegotiatorTest extends TestCase
{
    public function testPicksFirstAcceptedCandidateByPreference(): void
    {
        $candidates = [Format::AVIF, Format::WEBP];
        $driver = $this->driverSupporting(Format::AVIF, Format::WEBP);

        // client accepts both -> first candidate (AVIF) wins
        self::assertSame(Format::AVIF, FormatNegotiator::negotiate($candidates, 'image/avif,image/webp,image/*', $driver));
        // only webp accepted
        self::assertSame(Format::WEBP, FormatNegotiator::negotiate($candidates, 'image/webp,image/png', $driver));
    }

    public function testReturnsNullWhenNoCandidateAccepted(): void
    {
        $driver = $this->driverSupporting(Format::AVIF, Format::WEBP);

        self::assertNull(FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], 'text/html,image/png', $driver));
        self::assertNull(FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], '', $driver));
    }

    public function testSkipsCandidatesTheDriverCannotEncode(): void
    {
        // client accepts AVIF, but the driver only supports WebP -> falls back to the next eligible candidate
        $driver = $this->driverSupporting(Format::WEBP);

        self::assertSame(
            Format::WEBP,
            FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], 'image/avif,image/webp', $driver),
        );

        // driver supports neither -> no negotiation
        self::assertNull(
            FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], 'image/avif,image/webp', $this->driverSupporting()),
        );
    }

    public function testRealGdDriverSupportIsRespected(): void
    {
        $driver = new Driver();

        $result = FormatNegotiator::negotiate([Format::AVIF, Format::WEBP], 'image/avif,image/webp', $driver);

        // whatever the result, it must be a format this GD build can actually encode
        if (null !== $result) {
            self::assertTrue($driver->supports($result));
        } else {
            self::assertFalse($driver->supports(Format::AVIF));
            self::assertFalse($driver->supports(Format::WEBP));
        }
    }

    private function driverSupporting(Format ...$formats): DriverInterface
    {
        $driver = self::createStub(DriverInterface::class);
        $driver->method('supports')->willReturnCallback(
            static fn (mixed $identifier): bool => in_array($identifier, $formats, true),
        );

        return $driver;
    }
}
