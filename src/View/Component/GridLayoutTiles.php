<?php

namespace Redaxo\Core\View\Component;

/** Tiles of a minimum width, as many per row as fit, without breakpoints and widths of the columns. */
final class GridLayoutTiles implements GridLayout
{
    public function __construct(
        public GridTileSize $size = GridTileSize::Medium,
    ) {}
}
