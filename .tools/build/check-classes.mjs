// Finds classes in the backend markup (PHP of src/, pages/, fragments/) that neither have a rule in the backend
// styles nor are used by the backend scripts, so dead classes do not pile up again. Run with
// `npm run check:classes`.
//
// Only literal class names are found: `class="…"` attributes and `'class' => …` entries of HtmlAttributes.

import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

/** Classes for other targets than the backend styles: icons, state hooks of the body, frontend and error pages. */
const allowedPrefixes = ['rex-icon', 'fa-', 'fa', 'rex-mime', 'rex-is-', 'rex-code-'];
const skippedFiles = [
    'src/ErrorHandler.php', // error page with its own styles
    'src/Migration/WebRunner.php', // standalone page of the web migration
    'src/Util/VarDumper.php',
    'src/View/HtmlAttributes.php', // examples in doc comments
    'src/Content/CategoryNavigation.php', // navigation of the frontend
    'fragments/core/be_ooops.php',
    'fragments/core/fe_ooops.php',
];

function files(dir, extension) {
    return readdirSync(join(root, dir), { recursive: true, withFileTypes: true })
        .filter(entry => entry.isFile() && entry.name.endsWith(extension))
        .map(entry => relative(root, join(entry.parentPath, entry.name)));
}

const styles = files('assets/css/src', '.css')
    .filter(file => !file.includes('/compat/'))
    .map(file => readFileSync(join(root, file), 'utf8'))
    .join('\n');
const defined = new Set([...styles.matchAll(/\.([a-zA-Z][\w-]*)/g)].map(match => match[1]));
const scripts = files('assets/js/src', '.js').map(file => readFileSync(join(root, file), 'utf8')).join('\n');

const used = new Map();
for (const file of ['src', 'pages', 'fragments'].flatMap(dir => files(dir, '.php'))) {
    if (skippedFiles.includes(file)) {
        continue;
    }
    const php = readFileSync(join(root, file), 'utf8');
    const lists = [
        ...[...php.matchAll(/class=\\?["']([^"'<>$]*)["']/g)].map(match => match[1].split(/\s+/)),
        ...[...php.matchAll(/'class'\s*=>\s*\[([^\]]*)\]/g)].map(match => [...match[1].matchAll(/(?<!\$\w+\[)'([\w-]+)'/g)].map(item => item[1])),
        ...[...php.matchAll(/'class'\s*=>\s*'([^']*)'/g)].map(match => match[1].split(/\s+/)),
    ];
    for (const name of lists.flat()) {
        if (/^[a-z][\w-]*[a-z0-9]$/.test(name)) {
            used.set(name, [...(used.get(name) ?? []), file]);
        }
    }
}

let failures = 0;
for (const [name, where] of [...used].sort()) {
    if (defined.has(name) || allowedPrefixes.some(prefix => name.startsWith(prefix))) {
        continue;
    }
    if (new RegExp(`(?<![\\w-])${name}(?![\\w-])`).test(scripts)) {
        continue;
    }
    ++failures;
    console.log(`✖ .${name} has no style and no script: ${[...new Set(where)].join(', ')}`);
}

if (failures) {
    process.exitCode = 1;
} else {
    console.log('All classes of the backend markup are styled or scripted.');
}
