/*
 Theme changes: dispatches `rex:theme.change` (with `detail.theme`, 'light' or 'dark') on the body whenever the
 effective theme changes, be it by the system setting or by switching the body's `rex-theme-*` class
 */

const dark = matchMedia('(prefers-color-scheme: dark)');

function effectiveTheme() {
    const {classList} = document.body;
    if (classList.contains('rex-theme-light')) {
        return 'light';
    }
    if (classList.contains('rex-theme-dark')) {
        return 'dark';
    }
    return dark.matches ? 'dark' : 'light';
}

let current = null;

function update() {
    const theme = effectiveTheme();
    if (null !== current && theme !== current) {
        document.body.dispatchEvent(new CustomEvent('rex:theme.change', {bubbles: true, detail: {theme}}));
    }
    current = theme;
}

document.addEventListener('DOMContentLoaded', () => {
    update();
    dark.addEventListener('change', update);
    new MutationObserver(update).observe(document.body, {attributes: true, attributeFilter: ['class']});
});
