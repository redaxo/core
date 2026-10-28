/* Shell: main navigation on small screens, top bar hiding while scrolling down */

const smallScreen = matchMedia('(max-width: 991px)');

function toggleSidebar(open = !document.body.classList.contains('rex-sidebar-open'), {returnFocus = false} = {}) {
    const wasOpen = document.body.classList.contains('rex-sidebar-open');
    document.body.classList.toggle('rex-sidebar-open', open);
    // freeze the scroll position below the open navigation
    document.documentElement.style.overflowY = open ? 'hidden' : '';
    const toggle = document.getElementById('rex-js-nav-main-toggle');
    toggle?.setAttribute('aria-expanded', String(open));

    // the open navigation covers the page like a dialog: the rest is out of reach until it closes
    for (const element of document.querySelectorAll('.rex-main, .rex-footer')) {
        element.inert = open;
    }
    if (open && !wasOpen) {
        document.querySelector('#rex-js-nav-main a[href]')?.focus();
    } else if (!open && wasOpen && returnFocus) {
        toggle?.focus();
    }
}

const topbar = {
    previous: 0,
    lastToggle: null,

    update() {
        const bar = document.querySelector('.rex-topbar[data-variant="fixed"]');
        if (!bar) {
            return;
        }
        const max = document.body.scrollHeight - document.body.clientHeight;
        const current = Math.min(Math.max(window.scrollY, 0), max);
        this.lastToggle ??= current;

        if (!document.body.classList.contains('rex-sidebar-open')) {
            if (current > this.previous && current >= this.lastToggle + 150) {
                bar.dataset.concealed = '';
                this.lastToggle = current;
            } else if (current < this.previous && (current <= this.lastToggle - 300 || current < 50)) {
                delete bar.dataset.concealed;
                this.lastToggle = current;
            }
        }
        bar.toggleAttribute('data-elevated', current > 10);
        this.previous = current;
    },

    reset() {
        const bar = document.querySelector('.rex-topbar[data-variant="fixed"]');
        if (bar) {
            delete bar.dataset.concealed;
        }
        this.lastToggle = this.previous;
    },
};

document.addEventListener('click', event => {
    if (event.target.closest('#rex-js-nav-main-toggle')) {
        event.preventDefault();
        toggleSidebar();
        topbar.reset();
    } else if (event.target.closest('#rex-js-nav-main-backdrop')) {
        toggleSidebar(false, {returnFocus: true});
    }
});

document.addEventListener('keydown', event => {
    if ('Escape' === event.key && document.body.classList.contains('rex-sidebar-open')) {
        toggleSidebar(false, {returnFocus: true});
    }
});

let ticking = false;
window.addEventListener('scroll', () => {
    if (!ticking) {
        ticking = true;
        requestAnimationFrame(() => {
            topbar.update();
            ticking = false;
        });
    }
}, {passive: true});

smallScreen.addEventListener('change', () => {
    toggleSidebar(false);
    topbar.reset();
});

topbar.update();
