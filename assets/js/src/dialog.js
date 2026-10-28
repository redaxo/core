/* Dialogs: modal <dialog> boxes for confirmations and notices, plus the `data-confirm` attribute */

let count = 0;

/** Text as text node, nodes as they are. */
function contentOf(value) {
    return value instanceof Node ? value : document.createTextNode(String(value));
}

/**
 * Opens a modal dialog. Clicking a button closes it; `closed` resolves with the button's value ('' for Esc or the
 * close button).
 *
 *     const {closed} = dialog({title: 'Title', content: 'Text', buttons: [{label: 'OK', value: 'ok', primary: true}]});
 *
 * Strings are inserted as text, markup is passed as a `Node`. Without title, the content names the dialog.
 *
 * @param {{
 *     title?: string|Node,
 *     content: string|Node,
 *     buttons?: Array<{label: string|Node, icon?: string, value?: string, className?: string, primary?: boolean, onClick?: (event: MouseEvent) => void}>,
 *     className?: string,
 *     role?: 'dialog'|'alertdialog',
 *     size?: 'sm'|null,
 * }} options
 * @returns {{element: HTMLDialogElement, body: HTMLElement, close: (value?: string) => void, closed: Promise<string>}}
 */
export function dialog({title = '', content, buttons = [], className = '', role = 'dialog', size = null}) {
    const id = 'rex-dialog-' + ++count;
    const element = document.createElement('dialog');
    element.className = ('rex-dialog ' + className).trim();
    element.innerHTML = `
        <form method="dialog" class="rex-dialog-inner">
            <header class="rex-dialog-header">
                <h2 class="rex-dialog-title" id="${id}-title"></h2>
                <button class="rex-dialog-close" value=""><i class="rex-icon fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="rex-dialog-body" id="${id}-body"></div>
            <footer class="rex-dialog-footer"></footer>
        </form>`;
    if (size) {
        element.dataset.size = size;
    }
    if ('alertdialog' === role) {
        element.setAttribute('role', 'alertdialog');
    }

    const heading = element.querySelector('.rex-dialog-title');
    if (title) {
        heading.append(contentOf(title));
        element.setAttribute('aria-labelledby', heading.id);
    } else {
        heading.remove();
        element.setAttribute('aria-labelledby', id + '-body');
    }
    element.querySelector('.rex-dialog-close').setAttribute('aria-label', window.rex.i18n?.overlay_close ?? 'Close');

    const body = element.querySelector('.rex-dialog-body');
    body.append(contentOf(content));

    const footer = element.querySelector('.rex-dialog-footer');
    for (const {label, icon, value = '', className = '', primary = false, onClick} of buttons) {
        const button = document.createElement('button');
        button.className = ('rex-button ' + className).trim();
        if (primary) {
            button.dataset.variant = 'primary';
        }
        button.value = value;
        if (icon) {
            button.insertAdjacentHTML('beforeend', `<i class="rex-icon rex-icon-${CSS.escape(icon)}" aria-hidden="true"></i> `);
        }
        button.append(contentOf(label));
        button.autofocus = primary;
        if (onClick) {
            button.addEventListener('click', onClick);
        }
        footer.append(button);
    }
    if (!buttons.length) {
        footer.remove();
    }

    const closed = new Promise(resolve => element.addEventListener('close', () => {
        resolve(element.returnValue);
        element.remove();
    }, {once: true}));

    document.body.append(element);
    element.showModal();

    return {element, body, close: (value = '') => element.close(value), closed};
}

/** Asks for confirmation, resolves with true when the user confirms. */
export async function confirm(message) {
    const i18n = window.rex.i18n ?? {};
    const {closed} = dialog({
        content: Object.assign(document.createElement('p'), {textContent: message}),
        size: 'sm',
        role: 'alertdialog',
        buttons: [
            {label: i18n.dialog_cancel ?? 'Cancel', value: 'cancel'},
            {label: i18n.dialog_confirm ?? 'OK', value: 'confirm', primary: true},
        ],
    });

    return 'confirm' === await closed;
}

// Links, buttons and forms with `data-confirm="Question?"` ask before they take effect; once confirmed, the original
// action is triggered again and passes.
const confirmed = new WeakSet();

window.addEventListener('click', event => {
    const element = event.target.closest?.('a[data-confirm], button[data-confirm], input[data-confirm]');
    if (!element || confirmed.delete(element)) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    // the repeated click keeps the modifier keys, e.g. Ctrl/Cmd + click opens a link in a new tab
    const {ctrlKey, metaKey, shiftKey, altKey, button} = event;
    confirm(element.dataset.confirm).then(ok => {
        if (ok) {
            confirmed.add(element);
            element.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true, view: window, ctrlKey, metaKey, shiftKey, altKey, button}));
        }
    });
}, true);

window.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('form[data-confirm]') || confirmed.delete(form)) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    confirm(form.dataset.confirm).then(ok => {
        if (ok) {
            confirmed.add(form);
            form.requestSubmit(event.submitter);
        }
    });
}, true);
