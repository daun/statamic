import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { defineComponent, h, ref, watch } from 'vue';
import useHistoryState from '@/composables/history-state.js';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { on: () => () => {}, visit } }));

let wrappers = [];

beforeEach(() => {
    visit.mockClear();
    window.history.replaceState({ page: { component: 'Foo' }, scrollRegions: [] }, '', '/');
});

afterEach(() => {
    wrappers.forEach((wrapper) => wrapper.unmount());
    wrappers = [];
});

// Mounts an owner of the given key whose value is pushed to the history whenever it changes.
function mountOwner(key, initial) {
    const value = ref(initial);
    const restored = vi.fn((stored) => (value.value = stored));
    let history;

    const wrapper = mount(
        defineComponent({
            setup() {
                history = useHistoryState(key, restored);
                history.replace(value.value);
                watch(value, (value) => history.push(value));
                return () => h('div');
            },
        }),
    );

    wrappers.push(wrapper);

    return { value, restored, history, wrapper };
}

function popTo(state) {
    window.dispatchEvent(new PopStateEvent('popstate', { state }));
}

const entry = () => window.history.state.statamic;

test('entries store the values of all owners and leave out inertia page state', () => {
    mountOwner('folder', 'photos');
    const { history } = mountOwner('parameters', { page: 1 });

    history.push({ page: 2 }, '?page=2');

    expect(window.location.search).toBe('?page=2');
    expect(window.history.state.page).toBeUndefined();
    expect(window.history.state.scrollRegions).toEqual([]);
    expect(entry().values).toEqual({ folder: 'photos', parameters: { page: 2 } });
});

test('popping an entry restores each owner and keeps inertia from handling it', async () => {
    const folder = mountOwner('folder', 'photos');
    const parameters = mountOwner('parameters', { page: 1 });
    const inertia = vi.fn();
    window.addEventListener('popstate', inertia);

    popTo({ statamic: { ...entry(), values: { folder: 'videos', parameters: { page: 3 } } } });

    expect(folder.restored).toHaveBeenCalledWith('videos');
    expect(parameters.restored).toHaveBeenCalledWith({ page: 3 });
    expect(inertia).not.toHaveBeenCalled();
    expect(visit).not.toHaveBeenCalled();

    window.removeEventListener('popstate', inertia);
});

test('restoring an entry does not push another one', async () => {
    const { wrapper } = mountOwner('folder', 'photos');
    const pushSpy = vi.spyOn(window.history, 'pushState');

    popTo({ statamic: { ...entry(), values: { folder: 'videos' } } });
    await wrapper.vm.$nextTick();

    expect(pushSpy).not.toHaveBeenCalled();

    pushSpy.mockRestore();
});

test('pushes resume after an entry was restored', async () => {
    const { value, wrapper } = mountOwner('folder', 'photos');

    popTo({ statamic: { ...entry(), values: { folder: 'videos' } } });
    await wrapper.vm.$nextTick();

    value.value = 'music';
    await wrapper.vm.$nextTick();

    expect(entry().values.folder).toBe('music');
});

test('pushes include the values of the restored entry', async () => {
    const folder = mountOwner('folder', 'photos');
    mountOwner('parameters', { page: 1 });

    popTo({ statamic: { ...entry(), values: { folder: 'videos', parameters: { page: 3 } } } });
    await folder.wrapper.vm.$nextTick();

    folder.value.value = 'music';
    await folder.wrapper.vm.$nextTick();

    expect(entry().values).toEqual({ folder: 'music', parameters: { page: 3 } });
});

test('entries without stored state are left to inertia', () => {
    const { restored } = mountOwner('folder', 'photos');
    const inertia = vi.fn();
    window.addEventListener('popstate', inertia);

    popTo({ page: { component: 'Bar' } });

    expect(restored).not.toHaveBeenCalled();
    expect(inertia).toHaveBeenCalled();

    window.removeEventListener('popstate', inertia);
});

test('entries from an earlier page are loaded fresh', () => {
    const first = mountOwner('folder', 'photos');
    const earlier = entry();
    first.wrapper.unmount();
    wrappers = [];

    const { restored } = mountOwner('folder', 'videos');

    popTo({ statamic: earlier });

    expect(restored).not.toHaveBeenCalled();
    expect(visit).toHaveBeenCalledWith(window.location.href, { replace: true, preserveScroll: true });
});

test('entries are loaded fresh once their page is gone', () => {
    const { wrapper } = mountOwner('folder', 'photos');
    const earlier = entry();
    wrapper.unmount();
    wrappers = [];

    popTo({ statamic: earlier });

    expect(visit).toHaveBeenCalled();
});
