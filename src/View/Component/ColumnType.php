<?php

namespace Redaxo\Core\View\Component;

/** Kind of content of a {@see TableColumn}, which determines its width and alignment. */
enum ColumnType: string
{
    case Text = 'text';
    /** Narrow, centered column of an icon, often linked */
    case Icon = 'icon';
    /** Right aligned numbers as narrow as possible */
    case Id = 'id';
    case Number = 'number';
    /** Like {@see self::Number}, with room for an input in edit mode */
    case Priority = 'priority';
    /** Without line breaks */
    case Date = 'date';
    /** Links or buttons acting on the row, e.g. edit and delete */
    case Action = 'action';
    case Thumbnail = 'thumbnail';
}
