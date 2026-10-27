// Copies the third-party assets installed via npm into the committed `assets/` directory.
//
// REDAXO is consumed via Composer, so end users never run npm. The vendor files copied here are
// committed to the repo; this script only needs to run when bumping a dependency in package.json.

import { cpSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const modules = join(root, 'node_modules');
const assets = join(root, 'assets');

// Text assets are committed with LF (enforced via .gitattributes); some vendor files ship
// with CRLF, so normalize them on copy to keep `npm run build` output identical to what is
// committed. Binary files (fonts) are copied verbatim.
const textExtensions = new Set(['.css', '.js', '.map']);

function copy(from, to) {
    if (textExtensions.has(extname(from))) {
        writeFileSync(to, readFileSync(from, 'utf8').replace(/\r\n/g, '\n'));
    } else {
        cpSync(from, to);
    }
}

/** Single-file copies: [source relative to node_modules, target relative to assets]. */
const files = [
    // keeps the package layout, so the stylesheet finds the fonts in ../webfonts
    ['@fortawesome/fontawesome-free/css/all.min.css', 'fonts/font-awesome/css/all.min.css'],
];

/**
 * Copies with a transformation of the content: [source relative to node_modules, target relative to assets, transform].
 */
const transformed = [
    // icons with Font Awesome 4 names (e.g. stored in addon settings) work with `.rex-icon` just like with `.fa`
    [
        '@fortawesome/fontawesome-free/css/v4-shims.min.css',
        'fonts/font-awesome/css/v4-shims.min.css',
        css => css.replaceAll('.fa.', ':is(.fa,.rex-icon).'),
    ],
];

/** Whole-directory copies: [source dir relative to node_modules, target dir relative to assets]. */
const dirs = [
    ['@fortawesome/fontawesome-free/webfonts', 'fonts/font-awesome/webfonts'],
];

for (const [from, to] of files) {
    const target = join(assets, to);
    mkdirSync(dirname(target), { recursive: true });
    copy(join(modules, from), target);
    console.log(`copied ${to}`);
}

for (const [from, to, transform] of transformed) {
    const target = join(assets, to);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, transform(readFileSync(join(modules, from), 'utf8').replace(/\r\n/g, '\n')));
    console.log(`copied ${to}`);
}

for (const [from, to] of dirs) {
    const sourceDir = join(modules, from);
    const targetDir = join(assets, to);
    mkdirSync(targetDir, { recursive: true });
    for (const entry of readdirSync(sourceDir, { withFileTypes: true })) {
        if (entry.isFile()) {
            copy(join(sourceDir, entry.name), join(targetDir, entry.name));
            console.log(`copied ${to}/${entry.name}`);
        }
    }
}
