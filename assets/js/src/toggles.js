/*
 Toggles: dropdowns (`data-rex-dropdown`, and `data-toggle="dropdown"` of the Bootstrap 3 markup), collapsible
 elements (`data-toggle="collapse"`) and tabs (`data-toggle="tab"`).
 */

const TOGGLE = '[data-rex-dropdown], [data-toggle="dropdown"]';
const MENU = ':is(.rex-dropdown-menu, .dropdown-menu)';

// ---------------------------------------------------------------------------------------------------------------------
// Dropdowns: the toggle gets `aria-expanded`, its parent `.open` (for the Bootstrap 3 markup) and, when there is no
// room below, `data-placement="top"` and `.dropup`

function openDropdowns() {
    return document.querySelectorAll(`.open:has(> :is(${TOGGLE}))`);
}

function closeDropdowns(except = null) {
    for (const dropdown of openDropdowns()) {
        if (dropdown !== except) {
            dropdown.classList.remove('open');
            dropdown.querySelector(`:scope > :is(${TOGGLE})`).setAttribute('aria-expanded', 'false');
        }
    }
}

function toggleDropdown(toggle) {
    const dropdown = toggle.parentElement;
    const open = !dropdown.classList.contains('open');
    closeDropdowns(dropdown);

    if (open) {
        const menu = dropdown.querySelector(`:scope > ${MENU}`);
        const rect = dropdown.getBoundingClientRect();
        dropdown.classList.remove('dropup');
        delete dropdown.dataset.placement;
        dropdown.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        const height = menu?.offsetHeight ?? 0;
        if (innerHeight - rect.bottom < height && rect.top > height) {
            dropdown.classList.add('dropup');
            dropdown.dataset.placement = 'top';
        }
    } else {
        dropdown.classList.remove('open');
    }
    toggle.setAttribute('aria-expanded', String(open));
}

document.addEventListener('click', event => {
    const toggle = event.target.closest(TOGGLE);
    if (toggle) {
        event.preventDefault();
        if (!toggle.matches('.disabled, :disabled')) {
            toggleDropdown(toggle);
        }
        return;
    }
    // forms inside a dropdown menu stay open while being filled in
    if (!event.target.closest(`${MENU} form`)) {
        closeDropdowns();
    }
});

function dropdownItems(dropdown) {
    return [...dropdown.querySelectorAll(`:scope > ${MENU} li:not(.disabled) :is(a[href], button:not(:disabled))`)];
}

document.addEventListener('keydown', event => {
    // the arrow keys also open a closed dropdown, at its first item
    const closedToggle = event.target.closest(TOGGLE);
    if (closedToggle && !closedToggle.parentElement.classList.contains('open') && 'ArrowDown' === event.key) {
        event.preventDefault();
        toggleDropdown(closedToggle);
        dropdownItems(closedToggle.parentElement)[0]?.focus();
        return;
    }

    const dropdown = event.target.closest(`.open:has(> :is(${TOGGLE}))`);
    if (!dropdown) {
        return;
    }
    const toggle = dropdown.querySelector(`:scope > :is(${TOGGLE})`);

    if ('Escape' === event.key) {
        event.preventDefault();
        closeDropdowns();
        toggle.focus();
    } else if (('ArrowDown' === event.key || 'ArrowUp' === event.key) && !event.target.matches('input, select, textarea')) {
        // fields of a form in the menu keep their own arrow keys
        event.preventDefault();
        const items = dropdownItems(dropdown);
        const index = items.indexOf(document.activeElement);
        const next = 'ArrowDown' === event.key ? Math.min(index + 1, items.length - 1) : Math.max(index - 1, 0);
        items[next]?.focus();
    }
});

// an open dropdown closes once the focus leaves it
document.addEventListener('focusout', event => {
    const dropdown = event.target.closest(`.open:has(> :is(${TOGGLE}))`);
    if (dropdown && !dropdown.contains(event.relatedTarget) && null !== event.relatedTarget) {
        dropdown.classList.remove('open');
        dropdown.querySelector(`:scope > :is(${TOGGLE})`).setAttribute('aria-expanded', 'false');
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Collapsible elements: the targets (`data-target` with any selector, or `href` with an id) get `.in`, their toggles
// `.collapsed`; a target then dispatches `rex:collapse` with `detail.open`

function targetsOf(toggle) {
    const selector = toggle.dataset.target ?? toggle.getAttribute('href');
    if (!selector || (!toggle.dataset.target && !/^#[^\s]+$/.test(selector))) {
        return [];
    }
    try {
        return [...document.querySelectorAll(selector)];
    } catch {
        return [];
    }
}

function targetOf(toggle) {
    return targetsOf(toggle)[0] ?? null;
}

/** Shows or hides a collapsible element. */
export function collapse(target, open = !target.classList.contains('in')) {
    if (open === target.classList.contains('in')) {
        return;
    }
    target.classList.toggle('in', open);
    for (const toggle of document.querySelectorAll('[data-toggle="collapse"]')) {
        if (targetsOf(toggle).includes(target)) {
            toggle.classList.toggle('collapsed', !open);
            toggle.setAttribute('aria-expanded', String(open));
        }
    }
    target.dispatchEvent(new CustomEvent('rex:collapse', {bubbles: true, detail: {open}}));
}

document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-toggle="collapse"]');
    const targets = toggle ? targetsOf(toggle) : [];
    if (targets.length) {
        event.preventDefault();
        targets.forEach(target => collapse(target));
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Tabs: the link's list item and the pane it points to get `.active`; not used by the core, kept for addons with the
// Bootstrap 3 markup

document.addEventListener('click', event => {
    const link = event.target.closest('[data-toggle="tab"]');
    const pane = link && targetOf(link);
    if (!pane) {
        return;
    }
    event.preventDefault();

    const item = link.closest('li') ?? link;
    for (const sibling of item.parentElement.children) {
        sibling.classList.toggle('active', sibling === item);
        sibling.querySelector('[data-toggle="tab"]')?.setAttribute('aria-selected', String(sibling === item));
    }
    for (const sibling of pane.parentElement.children) {
        sibling.classList.toggle('active', sibling === pane);
        sibling.classList.toggle('in', sibling === pane && sibling.classList.contains('fade'));
    }
});
