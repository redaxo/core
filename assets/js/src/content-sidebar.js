/* Content edit page: remembers which sections of the sidebar are expanded */

import {collapse} from './toggles.js';

const key = 'structure_content_sidebar_status';

document.addEventListener('rex:ready', event => {
    const sidebar = event.target.matches('#rex-page-content-edit') && event.target.querySelector('#rex-js-main-sidebar');
    if (!sidebar) {
        return;
    }

    const panels = [...sidebar.children].filter(child => child.matches('section')).map(section => section.querySelector('div.collapse'));
    const store = () => {
        try {
            localStorage.setItem(key, JSON.stringify(panels.map(panel => String(Boolean(panel?.classList.contains('in'))))));
        } catch {}
    };

    let status = null;
    try {
        status = JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {}

    if (Array.isArray(status) && status.length === panels.length) {
        panels.forEach((panel, index) => panel && collapse(panel, 'true' === status[index]));
    } else {
        store();
    }
    sidebar.addEventListener('rex:collapse', store);
});
