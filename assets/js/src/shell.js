/* Shell: main navigation on small screens, top bar hiding while scrolling down */

const smallScreen = matchMedia('(max-width: 991px)');

function toggleSidebar(open = !document.body.classList.contains('rex-sidebar-open')) {
    document.body.classList.toggle('rex-sidebar-open', open);
    // freeze the scroll position below the open navigation
    document.documentElement.style.overflowY = open ? 'hidden' : '';
    document.getElementById('rex-js-nav-main-toggle')?.setAttribute('aria-expanded', String(open));
}

const topbar = {
    previous: 0,
    lastToggle: null,

    update() {
        const bar = document.querySelector('.rex-topbar--fixed');
        if (!bar) {
            return;
        }
        const max = document.body.scrollHeight - document.body.clientHeight;
        const current = Math.min(Math.max(window.scrollY, 0), max);
        this.lastToggle ??= current;

        if (!document.body.classList.contains('rex-sidebar-open')) {
            if (current > this.previous && current >= this.lastToggle + 150) {
                bar.classList.add('is-hidden');
                this.lastToggle = current;
            } else if (current < this.previous && (current <= this.lastToggle - 300 || current < 50)) {
                bar.classList.remove('is-hidden');
                this.lastToggle = current;
            }
        }
        bar.classList.toggle('is-elevated', current > 10);
        this.previous = current;
    },

    reset() {
        document.querySelector('.rex-topbar--fixed')?.classList.remove('is-hidden');
        this.lastToggle = this.previous;
    },
};

document.addEventListener('click', event => {
    if (event.target.closest('#rex-js-nav-main-toggle')) {
        event.preventDefault();
        toggleSidebar();
        topbar.reset();
    } else if (event.target.closest('#rex-js-nav-main-backdrop')) {
        toggleSidebar(false);
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
