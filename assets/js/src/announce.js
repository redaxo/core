/* Announcements: messages for screen readers about changes that are not visible as focus or page change */

const regions = new WeakMap();

function region(assertive) {
    // an open modal dialog makes the rest of the page inert, so the region lives in the topmost one
    const container = [...document.querySelectorAll('dialog[open]')].filter(dialog => dialog.matches(':modal')).at(-1) ?? document.body;
    const key = assertive ? 'assertive' : 'polite';
    const containerRegions = regions.get(container) ?? {};
    if (!containerRegions[key]?.isConnected) {
        const element = document.createElement('div');
        element.className = 'rex-u-visually-hidden';
        element.setAttribute('role', assertive ? 'alert' : 'status');
        container.append(element);
        containerRegions[key] = element;
        regions.set(container, containerRegions);
    }
    return containerRegions[key];
}

/**
 * Has screen readers read out a message, politely (once the reader is idle) or assertively (right away).
 *
 *     rex.announce('3 results');
 */
export function announce(message, {assertive = false} = {}) {
    const element = region(assertive);
    // a changed text is announced, so the same message twice in a row needs the region emptied in between
    element.textContent = '';
    requestAnimationFrame(() => element.textContent = message);
}
