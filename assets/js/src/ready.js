/* Ready event: announces new markup to the scripts that enhance it */

/**
 * Announces new markup: dispatches `rex:ready` on `root`, listeners on `document` find it as `event.target`.
 */
export function ready(root = document.body) {
    root.dispatchEvent(new CustomEvent('rex:ready', {bubbles: true}));
}
