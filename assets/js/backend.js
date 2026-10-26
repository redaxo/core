/*
 REDAXO backend: ready event, overlays, pickers and the picker widgets.

 Overlays load backend pages into stacked <dialog> elements of the same document, so overlays, widgets and the page
 below talk to each other through plain DOM events.
 */

const rex = window.rex;

// ---------------------------------------------------------------------------------------------------------------------
// Ready event

/**
 * Announces new markup: dispatches `rex:ready` on `root`, listeners on `document` find it as `event.target`.
 */
export function ready(root = document.body) {
    root.dispatchEvent(new CustomEvent('rex:ready', {bubbles: true}));
}

// ---------------------------------------------------------------------------------------------------------------------
// Overlays

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
        close.setAttribute('aria-label', rex.i18n?.overlay_close ?? 'Close');
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
            const heading = main.querySelector('.rex-page-header .page-header');
            this.title.textContent = heading?.querySelector('h1')?.textContent.trim() || doc.title.split(' · ')[0];
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

// ---------------------------------------------------------------------------------------------------------------------
// Pickers

function backendUrl(page, params) {
    const search = new URLSearchParams({page});
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    }
    return 'index.php?' + search;
}

/** Builds the overlay URL of a picker type; addons may register their own. */
export const pickers = {
    media: ({multiple, upload, types, category, file}) => backendUrl(upload ? 'mediapool/upload' : 'mediapool/media', {
        picker: multiple ? 'multiple' : 'single',
        types: types?.join(','),
        rex_file_category: category,
        file_name: file,
    }),
    article: ({multiple, category, language}) => backendUrl('linkmap', {
        picker: multiple ? 'multiple' : 'single',
        category_id: category,
        language,
    }),
};

/**
 * Opens a picker overlay and resolves with all picked items once it is closed.
 *
 *     const [media] = await rex.pick('media', {types: ['jpg']});
 *     rex.pick('article', {multiple: true, onPick: items => …});
 */
export function pick(type, options = {}) {
    if (!pickers[type]) {
        throw new Error(`Unknown picker type "${type}".`);
    }
    return overlay(pickers[type](options), options).closed;
}

// ---------------------------------------------------------------------------------------------------------------------
// Picker widgets (media, media list, article, article list)

function setValue(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('change', {bubbles: true}));
}

function syncList(widget) {
    const options = [...widget.querySelector('[data-rex-widget-list]').options];
    setValue(widget.querySelector('[data-rex-widget-value]'), options.map(option => option.value).join(','));
}

function addToList(widget, items) {
    const list = widget.querySelector('[data-rex-widget-list]');
    for (const {value, label} of items) {
        list.add(new Option(label, value));
    }
    syncList(widget);
}

function moveInList(widget, direction) {
    const list = widget.querySelector('[data-rex-widget-list]');
    const option = list.selectedOptions[0];
    if (!option) {
        return;
    }
    switch (direction) {
        case 'top': list.prepend(option); break;
        case 'up': option.previousElementSibling?.before(option); break;
        case 'down': option.nextElementSibling?.after(option); break;
        case 'bottom': list.append(option); break;
    }
    syncList(widget);
}

function selectedValue(widget) {
    return widget.querySelector('[data-rex-widget-list]').selectedOptions[0]?.value;
}

const widgetActions = {
    media: {
        open: (widget, options) => pick('media', {...options, onPick: ([item]) => setValue(widget.querySelector('[data-rex-widget-value]'), item.value)}),
        add: (widget, options) => widgetActions.media.open(widget, {...options, upload: true}),
        view: (widget, options) => {
            const file = widget.querySelector('[data-rex-widget-value]').value;
            if (file) {
                widgetActions.media.open(widget, {...options, file});
            }
        },
        remove: widget => setValue(widget.querySelector('[data-rex-widget-value]'), ''),
    },
    medialist: {
        open: (widget, options) => pick('media', {...options, file: selectedValue(widget), multiple: true, onPick: items => addToList(widget, items)}),
        add: (widget, options) => pick('media', {...options, upload: true, multiple: true, onPick: items => addToList(widget, items)}),
        view: (widget, options) => {
            const file = selectedValue(widget);
            if (file) {
                widgetActions.medialist.open(widget, options);
            }
        },
        remove: widget => {
            widget.querySelector('[data-rex-widget-list]').selectedOptions[0]?.remove();
            syncList(widget);
        },
        move: (widget, options, direction) => moveInList(widget, direction),
    },
    article: {
        open: (widget, options) => pick('article', {...options, onPick: ([item]) => {
            widget.querySelector('[data-rex-widget-label]').value = item.label;
            setValue(widget.querySelector('[data-rex-widget-value]'), item.value);
        }}),
        remove: widget => {
            widget.querySelector('[data-rex-widget-label]').value = '';
            setValue(widget.querySelector('[data-rex-widget-value]'), '');
        },
    },
    articlelist: {
        open: (widget, options) => pick('article', {...options, multiple: true, onPick: items => addToList(widget, items)}),
        remove: (widget, options) => widgetActions.medialist.remove(widget, options),
        move: (widget, options, direction) => moveInList(widget, direction),
    },
};

document.addEventListener('click', event => {
    const button = event.target.closest('[data-rex-widget-action]');
    const widget = button?.closest('[data-rex-widget]');
    if (!widget || button.disabled) {
        return;
    }
    event.preventDefault();
    const [action, argument] = button.dataset.rexWidgetAction.split(':');
    const options = JSON.parse(widget.dataset.rexWidgetOptions || '{}');
    widgetActions[widget.dataset.rexWidget]?.[action]?.(widget, options, argument);
});

// media preview while hovering a media widget
function showPreview(widget) {
    const preview = widget.querySelector('.rex-js-media-preview');
    const file = 'medialist' === widget.dataset.rexWidget
        ? widget.querySelector('[data-rex-widget-list]').selectedOptions[0]?.value
        : widget.querySelector('[data-rex-widget-value]').value;
    if (!preview || !file) {
        preview?.replaceChildren();
        return;
    }
    const img = preview.querySelector('img') ?? preview.appendChild(document.createElement('img'));
    img.src = file.endsWith('.svg')
        ? '../media/' + encodeURIComponent(file)
        : 'index.php?' + new URLSearchParams({rex_media_type: 'rex_media_small', rex_media_file: file});
}

document.addEventListener('mouseover', event => {
    const widget = event.target.closest('[data-rex-widget].rex-js-widget-preview');
    if (widget && !widget.contains(event.relatedTarget)) {
        showPreview(widget);
    }
});
document.addEventListener('mouseout', event => {
    const widget = event.target.closest('[data-rex-widget].rex-js-widget-preview');
    if (widget && !widget.contains(event.relatedTarget)) {
        widget.querySelector('.rex-js-media-preview')?.replaceChildren();
    }
});
document.addEventListener('change', event => {
    const widget = event.target.closest('[data-rex-widget="medialist"].rex-js-widget-preview');
    if (widget) {
        showPreview(widget);
    }
});

// links of popup pages (e.g. the media pool in the main navigation) open in an overlay
document.addEventListener('click', event => {
    const link = event.target.closest('a[data-rex-overlay]');
    if (link && 0 === event.button && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
        event.preventDefault();
        event.stopPropagation();
        overlay(link.href);
    }
});

Object.assign(rex, {ready, overlay, pick, pickers});
