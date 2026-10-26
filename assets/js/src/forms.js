/*
 Form helpers: accesskeys, `data-rex-check-all`, `data-rex-hides`, collection fields, the visibility toggle of password fields and
 submitting with Cmd/Ctrl + Enter
 */

// accesskeys work without modifier keys as long as no form field has the focus
document.addEventListener('keydown', event => {
    if (!window.rex.accesskeys || event.altKey || event.ctrlKey || event.metaKey || !/^[0-9a-z]$/.test(event.key)) {
        return;
    }
    if (event.target.closest?.('input, button, textarea, select, option, [contenteditable="true"]')) {
        return;
    }
    const target = document.querySelector(`input[accesskey="${event.key}"], button[accesskey="${event.key}"]`)
        ?? document.querySelector(`a[accesskey="${event.key}"]:is([onclick], [href]:not([href="#"]))`);
    if (target) {
        event.preventDefault();
        target.click();
    }
});

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
    } else if (button.matches('[data-rex-collection-remove]')) {
        button.closest('.rex-form-collection-item').remove();
        updateCollection(collection);
    } else {
        const item = button.closest('.rex-form-collection-item');
        if ('up' === button.dataset.rexCollectionMove) {
            item.previousElementSibling?.before(item);
        } else {
            item.nextElementSibling?.after(item);
        }
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
    addon.innerHTML = '<button type="button" class="btn btn-view" tabindex="-1"><i class="rex-icon rex-icon-view" aria-hidden="true"></i></button>';
    input.after(addon);

    addon.querySelector('button').addEventListener('click', event => {
        event.preventDefault();
        const visible = 'password' === input.type;
        input.type = visible ? 'text' : 'password';
        addon.querySelector('i').className = 'rex-icon ' + (visible ? 'rex-icon-hide' : 'rex-icon-view');
    });
}

document.addEventListener('rex:ready', event => {
    const root = event.target;
    root.querySelectorAll('input[data-rex-hides]').forEach(applyHides);
    root.querySelectorAll('[data-rex-collection]').forEach(updateCollection);
    root.querySelectorAll('input[type="password"]').forEach(addPasswordToggle);
    if (root !== document.body) {
        root.querySelector('[autofocus]')?.focus();
    }
});
