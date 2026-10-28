/*
 Overlays load backend pages into stacked <dialog> elements of the same document, so overlays, widgets and the page
 below talk to each other through plain DOM events.
 */

import {ready} from './ready.js';
import {announce} from './announce.js';

const stack = [];
let skipPopstate = 0;
let overlayCount = 0;

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
        this.dialog.setAttribute('aria-labelledby', `rex-overlay-title-${++overlayCount}`);
        this.dialog.innerHTML = `
            <header class="rex-overlay-header">
                <h2 class="rex-overlay-title" id="rex-overlay-title-${overlayCount}"></h2>
                <button type="button" class="rex-overlay-close" data-rex-overlay-close></button>
            </header>
            <div class="rex-overlay-body"></div>`;
        this.title = this.dialog.querySelector('.rex-overlay-title');
        // the target of the focus after loading another page into the overlay
        this.title.tabIndex = -1;
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
        const initial = !this.body.hasChildNodes();
        this.dialog.classList.add('is-loading');
        this.dialog.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {credentials: 'same-origin', ...init});
            if (401 === response.status || 'login' === pageOf(response.url)) {
                location.href = response.url;
                return;
            }
            // parsed in an inert template, as DOMParser checks the loaded page's styles against this page's CSP nonce
            const template = document.createElement('template');
            template.innerHTML = await response.text();
            const doc = template.content;
            const main = doc.getElementById('rex-js-page-main') ?? doc;

            // the page heading moves into the overlay header
            const heading = main.querySelector('.rex-pageheader-title');
            this.title.textContent = heading?.textContent.trim() || (doc.querySelector('title')?.textContent ?? '').split(' · ')[0];
            heading?.remove();

            this.url = response.url;
            this.body.replaceChildren(...main.childNodes);
            this.body.scrollTop = 0;
            ready(this.body);

            // the clicked link or button is gone with the old page, so the focus moves to the new page's title
            if (!initial && !this.body.contains(document.activeElement)) {
                this.title.focus();
                announce(this.title.textContent);
            }

            const auto = this.body.querySelector('[data-rex-pick-auto]');
            if (auto) {
                this.pick(auto, [itemOf(auto)]);
            }
        } catch (error) {
            const alert = Object.assign(document.createElement('div'), {className: 'rex-alert', textContent: String(error)});
            alert.dataset.variant = 'danger';
            alert.setAttribute('role', 'alert');
            this.body.replaceChildren(alert);
        } finally {
            this.dialog.classList.remove('is-loading');
            this.dialog.removeAttribute('aria-busy');
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

        event.stopPropagation();

        const url = new URL(href, this.url);
        if (this.keeps(url)) {
            event.preventDefault();
            this.load(url);
        } else if (url.origin === location.origin && url.pathname !== new URL(this.url).pathname) {
            // files like media open beside the backend, so the page underneath keeps its unsaved input
            event.preventDefault();
            window.open(url, '_blank', 'noopener');
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
        if (this.multiple) {
            announce(items.map(item => item.label).join(', ') + ': ' + (window.rex.i18n?.picker_picked ?? 'picked'));
        }
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
