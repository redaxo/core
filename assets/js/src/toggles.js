/*
 Toggles for the Bootstrap 3 compatible markup: dropdowns (`data-toggle="dropdown"`), collapsible elements
 (`data-toggle="collapse"`) and tabs (`data-toggle="tab"`).
 */

// ---------------------------------------------------------------------------------------------------------------------
// Dropdowns: the toggle's parent gets `.open`, `.dropup` when there is no room below

function openDropdowns() {
    return document.querySelectorAll('.open:has(> [data-toggle="dropdown"])');
}

function closeDropdowns(except = null) {
    for (const dropdown of openDropdowns()) {
        if (dropdown !== except) {
            dropdown.classList.remove('open');
            dropdown.querySelector(':scope > [data-toggle="dropdown"]').setAttribute('aria-expanded', 'false');
        }
    }
}

function toggleDropdown(toggle) {
    const dropdown = toggle.parentElement;
    const open = !dropdown.classList.contains('open');
    closeDropdowns(dropdown);

    if (open) {
        const menu = dropdown.querySelector(':scope > .dropdown-menu');
        const rect = dropdown.getBoundingClientRect();
        dropdown.classList.remove('dropup');
        dropdown.classList.add('open');
        const height = menu?.offsetHeight ?? 0;
        dropdown.classList.toggle('dropup', innerHeight - rect.bottom < height && rect.top > height);
    } else {
        dropdown.classList.remove('open');
    }
    toggle.setAttribute('aria-expanded', String(open));
}

document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-toggle="dropdown"]');
    if (toggle) {
        event.preventDefault();
        if (!toggle.matches('.disabled, :disabled')) {
            toggleDropdown(toggle);
        }
        return;
    }
    // forms inside a dropdown menu stay open while being filled in
    if (!event.target.closest('.dropdown-menu form')) {
        closeDropdowns();
    }
});

document.addEventListener('keydown', event => {
    const dropdown = event.target.closest('.open:has(> [data-toggle="dropdown"])');
    if (!dropdown) {
        return;
    }
    const toggle = dropdown.querySelector(':scope > [data-toggle="dropdown"]');

    if ('Escape' === event.key) {
        event.preventDefault();
        closeDropdowns();
        toggle.focus();
    } else if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
        event.preventDefault();
        const items = [...dropdown.querySelectorAll(':scope > .dropdown-menu li:not(.disabled) > a')];
        const index = items.indexOf(document.activeElement);
        const next = 'ArrowDown' === event.key ? Math.min(index + 1, items.length - 1) : Math.max(index - 1, 0);
        items[next]?.focus();
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Collapsible elements: the target (`data-target` or `href`) gets `.in`, its toggles `.collapsed`; the target then
// dispatches `rex:collapse` with `detail.open`

function targetOf(toggle) {
    const selector = toggle.dataset.target ?? toggle.getAttribute('href');
    return selector?.startsWith('#') ? document.querySelector(selector) : null;
}

/** Shows or hides a collapsible element. */
export function collapse(target, open = !target.classList.contains('in')) {
    if (open === target.classList.contains('in')) {
        return;
    }
    target.classList.toggle('in', open);
    for (const toggle of document.querySelectorAll('[data-toggle="collapse"]')) {
        if (targetOf(toggle) === target) {
            toggle.classList.toggle('collapsed', !open);
            toggle.setAttribute('aria-expanded', String(open));
        }
    }
    target.dispatchEvent(new CustomEvent('rex:collapse', {bubbles: true, detail: {open}}));
}

document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-toggle="collapse"]');
    const target = toggle && targetOf(toggle);
    if (target) {
        event.preventDefault();
        collapse(target);
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Tabs: the link's list item and the pane it points to get `.active`

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
