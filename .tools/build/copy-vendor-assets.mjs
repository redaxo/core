// Copies the third-party assets installed via npm into the committed `assets/` directory.
//
// REDAXO is consumed via Composer, so end users never run npm/sass. The compiled CSS (built
// by the `build:css` script) and the vendor JS/fonts copied here are committed to the repo;
// this script only needs to run when bumping a dependency in package.json.
//
// Hand-maintained vendor files that cannot be consumed as plain `<script>` assets without a
// bundler are intentionally NOT managed here and stay committed as-is:
//   - assets/jquery-ui.custom.min.js  (custom jQuery UI build, core + interaction modules)
//   - assets/clipboard-copy-element.js (@github/clipboard-copy-element ships ESM only)

import { transformSync } from 'esbuild';
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
    ['jquery/dist/jquery.js', 'jquery.js'],
    ['jquery/dist/jquery.min.js', 'jquery.min.js'],
    ['jquery/dist/jquery.min.map', 'jquery.min.map'],

    ['bootstrap-sass/assets/javascripts/bootstrap.js', 'js/bootstrap.js'],

    ['bootstrap-select/dist/css/bootstrap-select.min.css', 'css/bootstrap-select.min.css'],
    ['bootstrap-select/dist/js/bootstrap-select.min.js', 'js/bootstrap-select.min.js'],
    ['bootstrap-select/dist/js/bootstrap-select.min.js.map', 'js/bootstrap-select.min.js.map'],
    ['bootstrap-select/dist/js/i18n/defaults-de_DE.min.js', 'js/bootstrap-select-defaults-de_DE.min.js'],
    ['bootstrap-select/dist/js/i18n/defaults-en_US.min.js', 'js/bootstrap-select-defaults-en_US.min.js'],

    ['nouislider/dist/nouislider.js', 'noUiSlider/nouislider.js'],
    ['nouislider/dist/nouislider.min.js', 'noUiSlider/nouislider.min.js'],
    ['nouislider/dist/nouislider.css', 'noUiSlider/nouislider.css'],
    ['nouislider/dist/nouislider.min.css', 'noUiSlider/nouislider.min.css'],
];

/** Whole-directory copies: [source dir relative to node_modules, target dir relative to assets]. */
const dirs = [
    ['bootstrap-sass/assets/fonts/bootstrap', 'fonts/bootstrap'],
    ['@fortawesome/fontawesome-free/webfonts', 'fonts/font-awesome'],
];

for (const [from, to] of files) {
    const target = join(assets, to);
    mkdirSync(dirname(target), { recursive: true });
    copy(join(modules, from), target);
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

// jquery-pjax ships unminified only, so minify it ourselves to match the other vendor .min
// files (keeping the license banner).
const pjax = transformSync(readFileSync(join(modules, 'jquery-pjax/jquery.pjax.js'), 'utf8'), {
    minify: true,
    legalComments: 'inline',
});
writeFileSync(join(assets, 'jquery-pjax.min.js'), pjax.code);
console.log('minified jquery-pjax.min.js');
