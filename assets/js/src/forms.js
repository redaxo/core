import {announce} from './announce.js';

/*
 Form helpers: `data-rex-check-all`, `data-rex-hides`, `data-rex-autosubmit`, `data-rex-submit-params`,
 `data-rex-requires-checked`, collection fields, the visibility toggle of password fields and submitting with Cmd/Ctrl + Enter
 */

// submit forms with Cmd/Ctrl + Enter, preferring the apply button (e.g. when editing content)
document.addEventListener('keydown', event => {
    if ('Enter' !== event.key || !(event.metaKey || event.ctrlKey)) {
        return;
    }
    const form = event.target.closest?.('form');
    (form?.querySelector('.btn-apply') ?? form?.querySelector('[type="submit"]'))?.click();
}, true);

// a checkbox with `data-rex-check-all="name[]"` checks or unchecks all checkboxes of that name in its form
document.addEventListener('change', event => {
    const all = event.target;
    if (!all.matches('input[data-rex-check-all]')) {
        return;
    }
    for (const checkbox of all.form.querySelectorAll(`input[name="${CSS.escape(all.dataset.rexCheckAll)}"]`)) {
        if (checkbox.checked !== all.checked) {
            checkbox.checked = all.checked;
            checkbox.dispatchEvent(new Event('change', {bubbles: true}));
        }
    }
});

// a field with `data-rex-autosubmit` submits its form when it changes, e.g. a filter select
document.addEventListener('change', event => {
    if (event.target.matches('[data-rex-autosubmit]')) {
        event.target.form?.requestSubmit();
    }
});

// a submit button with `data-rex-submit-params='{"name": "value"}'` sends these fields along, e.g. an API call with its
// CSRF token
document.addEventListener('submit', event => {
    const form = event.target;
    form.querySelectorAll(':scope > input[data-rex-submit-param]').forEach(input => input.remove());
    const params = event.submitter?.dataset.rexSubmitParams;
    if (!params) {
        return;
    }
    for (const [name, value] of Object.entries(JSON.parse(params))) {
        const input = document.createElement('input');
        Object.assign(input, {type: 'hidden', name, value});
        input.dataset.rexSubmitParam = '';
        form.append(input);
    }
});

// `data-rex-requires-checked="name[]"` on a form or fieldset disables its submit buttons until a checkbox of that name
// is checked
function applyRequiresChecked(element) {
    const form = element.form ?? element.closest('form') ?? element;
    const name = CSS.escape(element.dataset.rexRequiresChecked);
    const disabled = !form.querySelector(`input[name="${name}"]:checked`);
    form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = disabled);
}

document.addEventListener('change', event => {
    const form = event.target.form;
    form?.querySelectorAll('[data-rex-requires-checked]').forEach(applyRequiresChecked);
    if (form?.matches('[data-rex-requires-checked]')) {
        applyRequiresChecked(form);
    }
});

// a checkbox with `data-rex-hides="#selector"` hides the target while it is checked, e.g. the item select next to an
// "all" checkbox
function applyHides(checkbox) {
    for (const element of document.querySelectorAll(checkbox.dataset.rexHides)) {
        element.style.display = checkbox.checked ? 'none' : '';
    }
}

document.addEventListener('change', event => {
    if (event.target.matches('input[data-rex-hides]')) {
        applyHides(event.target);
    }
});

// collection fields: add, remove and reorder items
function updateCollection(collection) {
    const max = parseInt(collection.dataset.rexCollectionMax, 10);
    const count = collection.querySelector(':scope > .rex-form-collection-items').children.length;
    const add = collection.querySelector(':scope > [data-rex-collection-add]');
    if (add) {
        add.style.display = isNaN(max) || count < max ? '' : 'none';
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-rex-collection-add], [data-rex-collection-remove], [data-rex-collection-move]');
    const collection = button?.closest('[data-rex-collection]');
    if (!collection) {
        return;
    }

    if (button.matches('[data-rex-collection-add]')) {
        const index = parseInt(collection.dataset.rexCollectionNext, 10);
        const html = collection.querySelector(':scope > template').innerHTML.split(collection.dataset.rexCollectionPlaceholder).join(String(index));
        const items = collection.querySelector(':scope > .rex-form-collection-items');
        items.insertAdjacentHTML('beforeend', html);
        collection.dataset.rexCollectionNext = String(index + 1);
        updateCollection(collection);
        window.rex.ready(items.lastElementChild);
        items.lastElementChild.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
        announce(window.rex.i18n?.collection_added ?? 'Item added');
    } else if (button.matches('[data-rex-collection-remove]')) {
        // the focus moves on to the next item, or to the add button after the last one
        const item = button.closest('.rex-form-collection-item');
        const next = item.nextElementSibling ?? item.previousElementSibling;
        item.remove();
        updateCollection(collection);
        (next?.querySelector('[data-rex-collection-remove]') ?? collection.querySelector(':scope > [data-rex-collection-add]'))?.focus();
        announce(window.rex.i18n?.collection_removed ?? 'Item removed');
    } else {
        const item = button.closest('.rex-form-collection-item');
        if ('up' === button.dataset.rexCollectionMove) {
            item.previousElementSibling?.before(item);
        } else {
            item.nextElementSibling?.after(item);
        }
        // moving the item takes the focus from its button
        button.focus();
    }
});

// password fields get a button showing the password
function addPasswordToggle(input) {
    if (input.dataset.rexPasswordToggle) {
        return;
    }
    input.dataset.rexPasswordToggle = 'true';

    if (!input.parentElement.matches('div.input-group')) {
        const group = document.createElement('div');
        group.className = 'input-group';
        input.before(group);
        group.append(input);
    }

    const addon = document.createElement('span');
    addon.className = 'input-group-btn';
    addon.innerHTML = '<button type="button" class="btn btn-view" aria-pressed="false"><i class="rex-icon rex-icon-view" aria-hidden="true"></i></button>';
    input.after(addon);

    const button = addon.querySelector('button');
    button.setAttribute('aria-label', window.rex.i18n?.password_show ?? 'Show password');
    if (input.id) {
        button.setAttribute('aria-controls', input.id);
    }
    button.addEventListener('click', event => {
        event.preventDefault();
        const visible = 'password' === input.type;
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.querySelector('i').className = 'rex-icon ' + (visible ? 'rex-icon-hide' : 'rex-icon-view');
    });
}

document.addEventListener('rex:ready', event => {
    const root = event.target;
    root.querySelectorAll('input[data-rex-hides]').forEach(applyHides);
    root.querySelectorAll('[data-rex-requires-checked]').forEach(applyRequiresChecked);
    root.querySelectorAll('[data-rex-collection]').forEach(updateCollection);
    root.querySelectorAll('input[type="password"]').forEach(addPasswordToggle);
    if (root !== document.body) {
        root.querySelector('[autofocus]')?.focus();
    }
});
