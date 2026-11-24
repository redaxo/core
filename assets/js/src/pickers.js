/* Pickers: overlays to choose media, articles … */

import {overlay} from './overlay.js';

function backendUrl(page, params) {
    const search = new URLSearchParams({page});
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    }
    return 'index.php?' + search;
}

/** Builds the overlay URL of a picker type; addons may register their own. */
export const pickers = {
    media: ({multiple, upload, types, category, file}) => backendUrl(upload ? 'mediapool/upload' : 'mediapool/media', {
        picker: multiple ? 'multiple' : 'single',
        types: types?.join(','),
        rex_file_category: category,
        file_name: file,
    }),
    article: ({multiple, category, language}) => backendUrl('linkmap', {
        picker: multiple ? 'multiple' : 'single',
        category_id: category,
        language,
    }),
};

/**
 * Opens a picker overlay and resolves with all picked items once it is closed.
 *
 *     const [media] = await rex.pick('media', {types: ['jpg']});
 *     rex.pick('article', {multiple: true, onPick: items => …});
 */
export function pick(type, options = {}) {
    if (!pickers[type]) {
        throw new Error(`Unknown picker type "${type}".`);
    }
    return overlay(pickers[type](options), options).closed;
}
