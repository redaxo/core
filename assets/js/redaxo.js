;(function (redaxo, window) {
    'use strict';

    var $ = window.$; // jQuery
    var document = window.document;

    // -----------------------------------------------------------------------

    // viewport
    // knows about screen size
    redaxo.viewport = (function () {
        var mode;

        var checkSize = function () {
            var size = getComputedStyle(document.querySelector('body'), ':after').getPropertyValue('content').replace(/"/g, '');
            if (size === 'min' || size === 'max') {
                mode = size;
            }
        };

        var isSmall = function () {
            checkSize();
            return mode === 'max'; // hint: it is small when it is max
        };

        // reveal
        return {
            isSmall: isSmall
        };
    })();

    // -----------------------------------------------------------------------

    // navigation
    // handles main navigation visibility
    redaxo.navigation = (function () {
        var active;
        var activeClass = 'rex-nav-main-is-visible';

        var isActive = function () {
            active = document.querySelector('body').classList.contains(activeClass);
            return active;
        };

        var toggle = function (mode) {
            if (typeof mode === 'undefined') {
                mode = !active;
            }
            if (mode) {
                document.querySelector('body').classList.add(activeClass);
                document.documentElement.style.overflowY = 'hidden'; // freeze scroll position
                active = true;
            } else {
                document.querySelector('body').classList.remove(activeClass);
                document.documentElement.style.overflowY = null;
                active = false;
            }
            return active;
        };

        var onViewportResize = function () {
            if (!redaxo.viewport.isSmall()) {
                toggle(false); // reset on desktop
            }
        };

        // reveal
        return {
            isActive: isActive,
            toggle: toggle,
            onViewportResize: onViewportResize
        };
    })();

    // -----------------------------------------------------------------------

    // navigation bar
    // handles top navigation bar position on scroll and on viewport resize
    redaxo.navigationBar = (function () {
        var navbarElm;
        var currentScrollPosition;
        var previousScrollPosition;
        var lastTogglePosition;
        var scrollDownUntilToggle = 150; // amount of px to be scrolled down before navbar hides
        var scrollUpUntilToggle = 300; // amount of px to be scrolled up before navbar appears
        var hiddenClass = 'rex-nav-top-is-hidden';
        var elevatedClass = 'rex-nav-top-is-elevated';

        var init = function () {
            navbarElm = document.querySelector('#rex-js-nav-top.rex-nav-top-is-fixed');
        };

        var show = function () {
            navbarElm.classList.remove(hiddenClass);
        };

        var hide = function () {
            navbarElm.classList.add(hiddenClass);
        };

        // update position and mode
        var update = function (scrollPosition) {
            scrollPosition = scrollPosition || window.scrollY;

            // require navbar element
            if (!navbarElm) {
                return false;
            }

            // save current scroll position
            currentScrollPosition = scrollPosition;
            var maxScrollPosition = document.body.scrollHeight - document.body.clientHeight;
            currentScrollPosition = Math.min(Math.max(scrollPosition, 0), maxScrollPosition); // clamp within range
            lastTogglePosition = lastTogglePosition || currentScrollPosition;

            // scrolling down
            if (!redaxo.navigation.isActive() && currentScrollPosition > previousScrollPosition && currentScrollPosition >= 0) {
                if (currentScrollPosition >= lastTogglePosition + scrollDownUntilToggle) {
                    hide();
                    lastTogglePosition = currentScrollPosition;
                }
            }

            // scrolling up
            if (!redaxo.navigation.isActive() && currentScrollPosition < previousScrollPosition) {
                if (currentScrollPosition <= lastTogglePosition - scrollUpUntilToggle || currentScrollPosition < 50) {
                    show();
                    lastTogglePosition = currentScrollPosition;
                }
            }

            // toggle elevated style on scroll position
            if (currentScrollPosition > 10) {
                navbarElm.classList.add(elevatedClass);
            } else {
                navbarElm.classList.remove(elevatedClass);
            }

            // save current scroll position
            previousScrollPosition = currentScrollPosition;
        };

        var reset = function () {
            show();
            lastTogglePosition = currentScrollPosition;
        };

        var onViewportResize = function () {
            if (!navbarElm) {
                return false;
            }
            reset();
        };

        // reveal
        return {
            init: init,
            update: update,
            reset: reset,
            onViewportResize: onViewportResize
        };
    })();

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

    var ticking = false;
    var timeout = false;

    // on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        redaxo.navigationBar.init();
        // initialize Observer to detect changes in darkmode/lightmode-themes on body-tag caused by JS
        if (redaxo.theme && null === redaxo.theme.observer) {
            redaxo.theme.observer = new MutationObserver(() => redaxo.theme.switched());
            redaxo.theme.observer.observe(document.body, {attributes: true, attributeFilter: ['class']});
        }
    });

    // on scroll
    window.addEventListener('scroll', function () {
        // use ticking to debounce rAF (https://developer.mozilla.org/en-US/docs/Web/API/Document/scroll_event#Examples)
        if (!ticking) {
            requestAnimationFrame(function () {
                // update navigations with current scroll position
                redaxo.navigationBar.update(window.scrollY);
                ticking = false;
            });
            ticking = true;
        }
    });

    // on resize
    window.addEventListener('resize', function () {
        // use timeout to debounce (https://css-tricks.com/snippets/jquery/done-resizing-event/)
        clearTimeout(timeout);
        timeout = setTimeout(function () {
            // trigger components
            redaxo.navigation.onViewportResize();
            redaxo.navigationBar.onViewportResize();
        }, 100);
    });

    // on click
    window.addEventListener('click', function (event) {
        // handle menu button
        if (event.target.matches('#rex-js-nav-main-toggle, #rex-js-nav-main-toggle *')) {
            event.preventDefault();
            redaxo.navigation.toggle();
            redaxo.navigationBar.reset();
        }
        // handle backdrop
        if (event.target.matches('#rex-js-nav-main-backdrop')) {
            redaxo.navigation.toggle(false); // close navigation
        }
    });

    // on PJAX success (jQuery)
    $(document).on('pjax:success', function () {
        redaxo.navigationBar.init();
        redaxo.navigationBar.update(window.scrollY); // update with current scroll position
        redaxo.navigation.toggle(false); // close navigation
    });

})(window.redaxo = window.redaxo || {}, window);
