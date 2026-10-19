// Must be imported first so that its popstate listener (the dirty navigation warning) runs before ours.
import './dirty-state.js';
import { nextTick, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * History entries for state that changes within a page, like a listing's
 * filters or the asset browser's folder, so the back and forward buttons
 * move between them without a page visit.
 */

const KEY = 'statamic';

const owners = new Map();
let session = null;
let sessionCount = 0;
let values = {};
let silenced = 0;

window.addEventListener('popstate', (event) => {
    const entry = event.state?.[KEY];

    if (!entry) return;

    event.stopImmediatePropagation();

    if (entry.session !== session) {
        router.visit(window.location.href, { replace: true, preserveScroll: true });
        return;
    }

    values = entry.values;

    silently(() => {
        owners.forEach((restore, key) => {
            if (key in values) restore(values[key]);
        });
    });
});

function write(method, key, value, url) {
    if (silenced || !session) return;

    values = { ...values, [key]: value };

    const { page, ...state } = window.history.state || {};

    window.history[method]({ ...state, [KEY]: { session, values } }, '', url);
}

/**
 * Run a callback without writing to the history, including from any watchers it triggers.
 */
function silently(callback) {
    silenced++;

    try {
        callback();
    } finally {
        nextTick(() => silenced--);
    }
}

function register(key, restore) {
    // A new session starts with the first owner of a page, so entries from an earlier page aren't restored into it.
    if (owners.size === 0) {
        session = `${performance.timeOrigin}-${++sessionCount}`;
        values = {};
    }

    owners.set(key, restore);
}

function unregister(key) {
    owners.delete(key);

    if (owners.size === 0) session = null;
}

/**
 * @param {string} key The key of the stored state this component owns.
 * @param {function} restore Called with the stored value when an entry is popped.
 */
export default function useHistoryState(key, restore) {
    register(key, restore);
    onBeforeUnmount(() => unregister(key));

    return {
        push: (value, url) => write('pushState', key, value, url),
        replace: (value, url) => write('replaceState', key, value, url),
        silently,
    };
}
