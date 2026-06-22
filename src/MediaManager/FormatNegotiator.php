<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;
use Intervention\Image\Interfaces\DriverInterface;

use function str_contains;

/**
 * Picks the best output format from a candidate list based on an HTTP `Accept` header.
 *
 * A candidate is only eligible if the active image driver can actually encode it — otherwise a
 * client accepting e.g. AVIF would trigger an encoder error on a driver built without AVIF support.
 *
 * @internal
 */
final class FormatNegotiator
{
    /**
     * @param list<Format> $candidates Candidate formats, best first
     * @return Format|null The first candidate the client accepts and the driver can encode, or `null`
     */
    public static function negotiate(array $candidates, string $accept, DriverInterface $driver): ?Format
    {
        foreach ($candidates as $format) {
            if (str_contains($accept, $format->mediaType()->value) && $driver->supports($format)) {
                return $format;
            }
        }

        return null;
    }
}
