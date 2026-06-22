<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

use function str_contains;

/**
 * Picks the best output format from a candidate list based on an HTTP `Accept` header.
 *
 * The candidate list passed in is expected to already be limited to formats the active driver can
 * actually encode (see {@see ImageManagerFactory::canEncode()}), so the negotiator only deals with
 * client preference.
 *
 * @internal
 */
final class FormatNegotiator
{
    /**
     * @param list<Format> $candidates Candidate formats, best first
     * @return Format|null The first candidate the client accepts, or `null` if none match
     */
    public static function negotiate(array $candidates, string $accept): ?Format
    {
        foreach ($candidates as $format) {
            if (str_contains($accept, $format->mediaType()->value)) {
                return $format;
            }
        }

        return null;
    }
}
