<?php

namespace Redaxo\Core\View\Component;

/**
 * Breakpoint of a component, applying from its width on (mobile first).
 *
 * The widths are those of the component itself, not of the viewport (container queries): a grid in a sidebar or dialog
 * follows its own width. `Md` to `Xl` match a component in the main area with open navigation at the viewport widths
 * 992, 1200 and 1400 pixels. Below 992 pixels the navigation is closed and the main area nearly as wide as the window,
 * so `Sm` is a width of its own, below `Md`.
 */
enum Breakpoint: string
{
    /** Every width, the base of the other breakpoints */
    case Xs = 'xs';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';

    /** Width in pixels from which the breakpoint applies */
    public function minWidth(): int
    {
        return match ($this) {
            self::Xs => 0,
            self::Sm => 576,
            self::Md => 712,
            self::Lg => 890,
            self::Xl => 1090,
        };
    }
}
