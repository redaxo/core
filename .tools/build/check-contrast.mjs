// Checks the contrast of the color tokens (assets/css/src/tokens.css) in the light and the dark scheme.
//
// Each pair below is a foreground token as it is used on a background token; text needs 4.5:1, the borders of input
// fields and the focus indicator 3:1 (WCAG 1.4.3 and 1.4.11). Run with `npm run check:contrast`.

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const css = readFileSync(join(root, 'assets/css/src/tokens.css'), 'utf8');

const TEXT = 4.5;
const UI = 3;

/** [foreground, backgrounds, minimum ratio] */
const pairs = [
    ['text', ['bg', 'surface', 'surface-subtle', 'surface-muted', 'surface-raised', 'control'], TEXT],
    ['text-muted', ['bg', 'surface', 'surface-subtle', 'surface-muted', 'surface-raised'], TEXT],
    ['link', ['bg', 'surface', 'surface-subtle', 'surface-raised'], TEXT],
    ['link-hover', ['bg', 'surface', 'surface-muted', 'accent-subtle', 'accent-subtle-hover'], TEXT],
    ['on-accent', ['accent', 'accent-hover'], TEXT],
    ['on-success', ['success', 'success-hover'], TEXT],
    ['success-text', ['bg', 'surface', 'success-subtle'], TEXT],
    ['on-warning', ['warning', 'warning-hover'], TEXT],
    ['warning-text', ['bg', 'surface'], TEXT],
    ['on-danger', ['danger', 'danger-hover'], TEXT],
    ['danger-text', ['bg', 'surface', 'danger-subtle'], TEXT],
    ['on-neutral', ['neutral', 'neutral-hover'], TEXT],
    ['inverse-text', ['inverse-bg', 'inverse-bg-strong'], TEXT],
    ['inverse-text-strong', ['inverse-bg', 'inverse-bg-strong'], TEXT],
    ['inverse-link', ['inverse-bg', 'inverse-bg-strong'], TEXT],
    ['inverse-bg-strong', ['inverse-text', 'inverse-text-strong'], TEXT],
    ['border-input', ['bg', 'surface', 'surface-subtle'], UI],
    ['focus', ['bg', 'surface', 'surface-subtle'], UI],
];

/** Light and dark value of each `--rex-color-*` token. */
const tokens = new Map();
for (const [, name, value] of css.matchAll(/--rex-color-([\w-]+):\s*([^;]+);/g)) {
    const match = value.match(/^light-dark\((.+),\s*(.+)\)$/);
    tokens.set(name, match ? [match[1], match[2]] : [value, value]);
}

function parse(color) {
    let match = color.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (match) {
        const hex = 3 === match[1].length ? [...match[1]].map(c => c + c).join('') : match[1];
        return [0, 2, 4].map(i => parseInt(hex.slice(i, i + 2), 16));
    }
    match = color.match(/^rgb\(([\d.]+) ([\d.]+) ([\d.]+)\)$/);
    if (match) {
        return match.slice(1, 4).map(Number);
    }
    throw new Error(`Unsupported color "${color}", only opaque hex and rgb() colors can be checked.`);
}

function luminance(color) {
    const [r, g, b] = parse(color).map(value => {
        value /= 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function ratio(a, b) {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (high + 0.05) / (low + 0.05);
}

let failures = 0;
for (const [foreground, backgrounds, minimum] of pairs) {
    for (const background of backgrounds) {
        for (const [index, scheme] of ['light', 'dark'].entries()) {
            const fg = tokens.get(foreground)?.[index];
            const bg = tokens.get(background)?.[index];
            if (!fg || !bg) {
                throw new Error(`Unknown token in pair ${foreground} on ${background}.`);
            }
            const value = ratio(fg, bg);
            if (value < minimum) {
                ++failures;
                console.log(`✖ ${scheme}: --rex-color-${foreground} (${fg}) on --rex-color-${background} (${bg}): ${value.toFixed(2)}:1, needs ${minimum}:1`);
            }
        }
    }
}

if (failures) {
    process.exitCode = 1;
} else {
    console.log('All color token pairs reach their contrast.');
}
