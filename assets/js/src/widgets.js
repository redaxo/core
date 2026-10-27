/* Picker widgets (media, media list, article, article list) */

import {overlay} from './overlay.js';
import {pick} from './pickers.js';

function setValue(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('change', {bubbles: true}));
}

function syncList(widget) {
    const options = [...widget.querySelector('[data-rex-widget-list]').options];
    setValue(widget.querySelector('[data-rex-widget-value]'), options.map(option => option.value).join(','));
}

// media lists show the filenames, like the saved entries
function byFilename(items) {
    return items.map(({value}) => ({value, label: value}));
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
        open: (widget, options) => pick('media', {...options, file: selectedValue(widget), multiple: true, onPick: items => addToList(widget, byFilename(items))}),
        add: (widget, options) => pick('media', {...options, upload: true, multiple: true, onPick: items => addToList(widget, byFilename(items))}),
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
