;(function (redaxo, window) {
    'use strict';

    var document = window.document;

    // -----------------------------------------------------------------------

    // Theme and Darkmode
    // prerequisites to detect darkmode-changes and have current settings simply available
    if (typeof rex === 'object' && rex.theme && !redaxo.theme && window.matchMedia) {
        redaxo.theme = {
            matchMediaDark: window.matchMedia('(prefers-color-scheme: dark)'),
            current: rex.theme,
            switched: function () {
                let prevTheme = this.current;
                if (document.body.classList.contains('rex-theme-light')) {
                    this.current = 'light';
                } else if (document.body.classList.contains('rex-theme-dark')) {
                    this.current = 'dark';
                } else { //auto
                    this.current = this.matchMediaDark.matches ? 'dark' : 'light';
                }
                if (prevTheme === this.current) return false;
                document.body.dispatchEvent(new CustomEvent('rex:theme.change', {detail: {theme: this.current}, bubbles: true}));
                return true;
            },
            observer: null,
        };
        // finalize initial status in case of auto-theme
        if ('auto' === redaxo.theme.current) {
            redaxo.theme.current = redaxo.theme.matchMediaDark.matches ? 'dark' : 'light';
        }
        // Detect future darkmode-change on system-level
        redaxo.theme.matchMediaDark.addEventListener('change', () => redaxo.theme.switched(), true);
    }


    // -----------------------------------------------------------------------

    // on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        // initialize Observer to detect changes in darkmode/lightmode-themes on body-tag caused by JS
        if (redaxo.theme && null === redaxo.theme.observer) {
            redaxo.theme.observer = new MutationObserver(() => redaxo.theme.switched());
            redaxo.theme.observer.observe(document.body, {attributes: true, attributeFilter: ['class']});
        }
    });

})(window.redaxo = window.redaxo || {}, window);
