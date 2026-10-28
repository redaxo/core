/* Announcements: messages for screen readers about changes that are not visible as focus or page change */

const regions = {};

function region(assertive) {
    const key = assertive ? 'assertive' : 'polite';
    if (!regions[key]?.isConnected) {
        const element = document.createElement('div');
        element.className = 'rex-visually-hidden';
        element.setAttribute('role', assertive ? 'alert' : 'status');
        document.body.append(element);
        regions[key] = element;
    }
    return regions[key];
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
