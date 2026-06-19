<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

/**
 * Default output quality per image format, resolved in three tiers:
 *
 * 1. core defaults (the `match` below),
 * 2. project overrides via {@see self::set()} (e.g. in the project's boot),
 * 3. per-type override via {@see MediaResponse::setQuality()}.
 *
 * Only lossy formats have a quality; lossless formats (PNG, GIF, …) return `null`.
 */
final class MediaQuality
{
    /** @var array<string, int> Project overrides keyed by {@see Format} name */
    private static array $overrides = [];

    public static function set(Format $format, int $quality): void
    {
        self::$overrides[$format->name] = $quality;
    }

    /** @internal */
    public static function get(Format $format): ?int
    {
        return self::$overrides[$format->name] ?? match ($format) {
            Format::JPEG => 80,
            Format::WEBP => 85,
            Format::AVIF => 60,
            default => null,
        };
    }
}
