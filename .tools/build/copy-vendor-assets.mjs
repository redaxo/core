// Copies the third-party assets installed via npm into the committed `assets/` directory.
//
// REDAXO is consumed via Composer, so end users never run npm. The vendor files copied here are
// committed to the repo; this script only needs to run when bumping a dependency in package.json.

import { cpSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const modules = join(root, 'node_modules');
const assets = join(root, 'assets');

// Text assets are committed with LF (enforced via .gitattributes); some vendor files ship
// with CRLF, so normalize them on copy to keep `npm run build` output identical to what is
// committed. Binary files (fonts) are copied verbatim.
const textExtensions = new Set(['.css', '.js', '.map']);

function copy(from, to, transform = content => content) {
    if (textExtensions.has(extname(from))) {
        writeFileSync(to, transform(readFileSync(from, 'utf8').replace(/\r\n/g, '\n')));
    } else {
        cpSync(from, to);
    }
}

// Font Awesome: all supported browsers load woff2, so the truetype fallbacks are dropped, and with them the font of
// the v4 shims, which the backend no longer ships
const fontAwesomeCss = css => css
    .replace(/,url\([^)]*\.ttf\) format\("truetype"\)/g, '')
    .replace(/@font-face\{[^}]*fa-v4compatibility[^}]*\}/g, '');
const fontAwesomeFonts = name => name.endsWith('.woff2') && !name.startsWith('fa-v4compatibility');

/** Single-file copies: [source relative to node_modules, target relative to assets, transform of the content]. */
const files = [
    // keeps the package layout, so the stylesheet finds the fonts in ../webfonts
    ['@fortawesome/fontawesome-free/css/all.min.css', 'fonts/font-awesome/css/all.min.css', fontAwesomeCss],
];

/** Whole-directory copies: [source dir relative to node_modules, target dir relative to assets, filter of the file names]. */
const dirs = [
    ['@fortawesome/fontawesome-free/webfonts', 'fonts/font-awesome/webfonts', fontAwesomeFonts],
];

for (const [from, to, transform] of files) {
    const target = join(assets, to);
    mkdirSync(dirname(target), { recursive: true });
    copy(join(modules, from), target, transform);
    console.log(`copied ${to}`);
}

for (const [from, to, filter = () => true] of dirs) {
    const sourceDir = join(modules, from);
    const targetDir = join(assets, to);
    rmSync(targetDir, { recursive: true, force: true });
    mkdirSync(targetDir, { recursive: true });
    for (const entry of readdirSync(sourceDir, { withFileTypes: true })) {
        if (entry.isFile() && filter(entry.name)) {
            copy(join(sourceDir, entry.name), join(targetDir, entry.name));
            console.log(`copied ${to}/${entry.name}`);
        }
    }
}
