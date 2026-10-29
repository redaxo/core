/*
 REDAXO backend scripts, bundled into assets/js/backend.js (npm run build:backend-js).

 The modules enhance the markup on their own (delegated listeners, `rex:ready`); `window.rex` exposes the API for
 other scripts.
 */

import {ready} from './ready.js';
import {announce} from './announce.js';
import {overlay} from './overlay.js';
import {pick, pickers} from './pickers.js';
import {dialog, confirm} from './dialog.js';
import {collapse} from './toggles.js';
import './shell.js';
import './widgets.js';
import './select.js';
import './forms.js';
import './content-sidebar.js';
import './session-timeout.js';
import './theme.js';
import './webauthn.js';
import './history.js';
import '@github/clipboard-copy-element';

document.addEventListener('clipboard-copy', () => announce(window.rex.i18n?.clipboard_copied ?? 'Copied'));

Object.assign(window.rex, {ready, announce, overlay, pick, pickers, dialog, confirm, collapse});

// after all deferred scripts, so their `rex:ready` listeners see the initial page too
document.addEventListener('DOMContentLoaded', () => ready(document.body));
