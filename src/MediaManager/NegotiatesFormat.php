<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

/**
 * Opt-in for content negotiation: a {@see MediaType} implementing this interface lets the engine
 * pick the output format based on the client's `Accept` header.
 *
 * The engine resolves the format *before* processing (so cached variants can be served without
 * re-processing), stores each negotiated format as a separate cache file, and sends `Vary: Accept`
 * so browsers and CDNs cache per format. If the client accepts none of the candidates, the source
 * (or the type's own) format is kept.
 *
 * Example — serve AVIF/WebP to clients that support them, otherwise the original format:
 *
 *     public function negotiableFormats(): array
 *     {
 *         return [Format::AVIF, Format::WEBP];
 *     }
 */
interface NegotiatesFormat extends MediaType
{
    /** @return list<Format> Candidate output formats, best first; the first the client accepts wins. */
    public function negotiableFormats(): array;
}
