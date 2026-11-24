<?php

namespace Redaxo\Core\View\Component;

/** Minimum width of the tiles of a {@see GridLayoutTiles}. */
enum GridTileSize: string
{
    /** 12rem */
    case Small = 'sm';
    /** 16rem */
    case Medium = 'md';
    /** 24rem */
    case Large = 'lg';
}
