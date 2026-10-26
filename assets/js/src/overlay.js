/*
 Overlays load backend pages into stacked <dialog> elements of the same document, so overlays, widgets and the page
 below talk to each other through plain DOM events.
 */

import {ready} from './ready.js';

const stack = [];
let skipPopstate = 0;

function pageOf(url) {
    return new URL(url, location.href).searchParams.get('page') ?? '';
}

/** Top-level page an overlay stays in, e.g. `mediapool`; links to other pages leave the overlay. */
function scopeOf(url) {
    return pageOf(url).split('/')[0];
}

class Overlay {
    /**
     * @param {string|URL} url
     * @param {{multiple?: boolean, onPick?: (items: Array<{value: string, label: string}>) => void}} options
     */
    constructor(url, {multiple = false, onPick = null} = {}) {
        this.url = new URL(url, location.href).toString();
        this.scope = scopeOf(this.url);
        this.multiple = multiple;
        this.onPick = onPick;
        this.picked = [];
        this.closed = new Promise(resolve => this.resolveClosed = resolve);

        this.dialog = document.createElement('dialog');
        this.dialog.className = 'rex-overlay';
        this.dialog.innerHTML = `
            <header class="rex-overlay-header">
                <h2 class="rex-overlay-title"></h2>
                <button type="button" class="rex-overlay-close" data-rex-overlay-close></button>
            </header>
            <div class="rex-overlay-body"></div>`;
        this.title = this.dialog.querySelector('.rex-overlay-title');
        this.body = this.dialog.querySelector('.rex-overlay-body');
        // focus the scrollable content on open, so keys scroll it and the close button shows no focus ring
        this.body.tabIndex = -1;
        this.body.autofocus = true;
        const close = this.dialog.querySelector('.rex-overlay-close');
        close.setAttribute('aria-label', window.rex.i18n?.overlay_close ?? 'Close');
        close.innerHTML = '<i class="rex-icon fa-solid fa-xmark" aria-hidden="true"></i>';

        this.dialog.addEventListener('cancel', event => {
            event.preventDefault();
            this.close();
        });
        this.dialog.addEventListener('click', event => this.onClick(event));
        this.dialog.addEventListener('submit', event => this.onSubmit(event));

        document.body.append(this.dialog);
        this.dialog.showModal();
        stack.push(this);
        history.pushState({rexOverlay: stack.length}, '');

        this.load(this.url);
    }

    async load(url, init = {}) {
        this.dialog.classList.add('is-loading');
        try {
            const response = await fetch(url, {credentials: 'same-origin', ...init});
            if (401 === response.status || 'login' === pageOf(response.url)) {
                location.href = response.url;
                return;
            }
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const main = doc.getElementById('rex-js-page-main') ?? doc.body;

            // the page heading moves into the overlay header
            const heading = main.querySelector('.rex-pageheader__title');
            this.title.textContent = heading?.textContent.trim() || doc.title.split(' · ')[0];
            heading?.remove();

            this.url = response.url;
            this.body.replaceChildren(...main.childNodes);
            this.body.scrollTop = 0;
            ready(this.body);

            const auto = this.body.querySelector('[data-rex-pick-auto]');
            if (auto) {
                this.pick(auto, [itemOf(auto)]);
            }
        } catch (error) {
            this.body.textContent = String(error);
        } finally {
            this.dialog.classList.remove('is-loading');
        }
    }

    /** Whether a URL is loaded inside this overlay (instead of leaving it for the main window). */
    keeps(url) {
        return url.origin === location.origin
            && url.pathname === new URL(this.url).pathname
            && scopeOf(url) === this.scope;
    }

    onClick(event) {
        if (event.target.closest('[data-rex-overlay-close]')) {
            this.close();
            return;
        }

        const trigger = event.target.closest('[data-rex-pick], [data-rex-pick-checked]');
        if (trigger) {
            event.preventDefault();
            const items = trigger.hasAttribute('data-rex-pick-checked')
                ? [...this.body.querySelectorAll(`input[name="${CSS.escape(trigger.dataset.rexPickChecked)}"]:checked`)]
                    .map(input => ({value: input.value, label: input.value}))
                : [itemOf(trigger)];
            this.pick(trigger, items);
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey
            || link.target || link.hasAttribute('download') || link.hasAttribute('data-rex-overlay')) {
            return;
        }
        const href = link.getAttribute('href');
        if (href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }

        // confirmation is asked here, as this handler runs before the delegated one on the document
        event.stopPropagation();
        if (link.dataset.confirm && !confirm(link.dataset.confirm)) {
            event.preventDefault();
            return;
        }

        const url = new URL(href, this.url);
        if (this.keeps(url)) {
            event.preventDefault();
            this.load(url);
        } else if (url.origin === location.origin) {
            event.preventDefault();
            closeAll(true);
            location.href = url;
        }
    }

    onSubmit(event) {
        const form = event.target;
        const url = new URL(event.submitter?.getAttribute('formaction') || form.getAttribute('action') || this.url, this.url);
        if (event.defaultPrevented || form.target || !this.keeps(url)) {
            return;
        }

        event.stopPropagation();
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        event.preventDefault();

        const data = new FormData(form, event.submitter);
        const method = (event.submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'get').toUpperCase();
        if ('GET' === method) {
            url.search = new URLSearchParams([...data].filter(([, value]) => 'string' === typeof value)).toString();
            this.load(url);
        } else {
            this.load(url, {method, body: data});
        }
    }

    /** Hands the items to the opener, unless a `rex:pick` listener cancels; a single picker closes afterwards. */
    pick(trigger, items) {
        if (!items.length) {
            return;
        }
        const event = new CustomEvent('rex:pick', {bubbles: true, cancelable: true, detail: {items, overlay: this}});
        if (!trigger.dispatchEvent(event)) {
            return;
        }

        this.picked.push(...items);
        this.onPick?.(items);
        trigger.classList.add('rex-is-picked');
        if (!this.multiple) {
            this.close();
        }
    }

    close(fromHistory = false) {
        const index = stack.indexOf(this);
        if (index < 0) {
            return;
        }
        stack.splice(index, 1);
        this.dialog.close();
        this.dialog.remove();
        this.resolveClosed(this.picked);
        if (!fromHistory) {
            ++skipPopstate;
            history.back();
        }
    }
}

function itemOf(element) {
    return {value: element.dataset.rexPick, label: element.dataset.rexPickLabel || element.dataset.rexPick};
}

function closeAll(keepHistory = false) {
    while (stack.length) {
        stack.at(-1).close(keepHistory);
    }
}

window.addEventListener('popstate', () => {
    if (skipPopstate > 0) {
        --skipPopstate;
        return;
    }
    stack.at(-1)?.close(true);
});

/** Opens a backend page in an overlay. */
export function overlay(url, options = {}) {
    return new Overlay(url, options);
}
