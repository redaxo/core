/*
 REDAXO backend scripts, bundled into assets/js/backend.js (npm run build:backend-js).

 The modules enhance the markup on their own (delegated listeners, `rex:ready`); `window.rex` exposes the API for
 other scripts.
 */

import {ready} from './ready.js';
import {overlay} from './overlay.js';
import {pick, pickers} from './pickers.js';
import {dialog, confirm} from './dialog.js';
import {collapse} from './toggles.js';
import './shell.js';
import './widgets.js';
import './content-sidebar.js';
import './session-timeout.js';

Object.assign(window.rex, {ready, overlay, pick, pickers, dialog, confirm, collapse});
