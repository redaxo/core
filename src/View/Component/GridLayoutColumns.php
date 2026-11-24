<?php

namespace Redaxo\Core\View\Component;

/**
 * Raster of 12 tracks, the default layout of a {@see Grid}.
 *
 * Each column states its width and start in tracks ({@see GridColumnRule}); without a width it spans all tracks, and
 * columns wrap into the next row when their widths exceed 12.
 */
final class GridLayoutColumns implements GridLayout
{
    public const int COLUMNS = 12;
}
