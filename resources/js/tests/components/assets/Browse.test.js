import { mount } from '@vue/test-utils';
import { afterEach, expect, test, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import * as Globals from '@/bootstrap/globals';
import Browse from '@/pages/assets/Browse.vue';

Object.keys(Globals).forEach((fn) => (window[fn] = Globals[fn]));

window.Statamic = { $config: { get: (key) => (key === 'cpUrl' ? '/cp' : undefined) } };

// Mirrors how the real asset browser reports navigation to the page.
const AssetBrowser = defineComponent({
    props: ['selectedPath', 'initialEditingAssetId'],
    emits: ['navigated'],
    data() {
        return { path: this.selectedPath, editedAssetId: this.initialEditingAssetId };
    },
    watch: {
        editedAssetId(id) {
            const basename = id?.split('::')[1];
            this.$emit('navigated', id ? [this.path, basename].filter((value) => value != '/').join('/') + '/edit' : this.path);
        },
        selectedPath(path) {
            if (!path.endsWith('/edit')) this.path = path;
        },
    },
    methods: {
        edit(id) {
            this.editedAssetId = id;
        },
        selectFolder(path) {
            this.path = path;
            this.$emit('navigated', path);
        },
    },
    render: () => h('div'),
});

let wrapper;

afterEach(() => wrapper?.unmount());

function mountBrowse(url) {
    window.history.replaceState({ page: { component: 'assets/Browse' } }, '', url);

    wrapper = mount(Browse, {
        props: { container: { id: 'main', title: 'Main' }, folder: '/', columns: [], filters: [], editing: null },
        global: {
            stubs: { Head: true, DocsCallout: true },
            components: { AssetBrowser },
            mocks: { $config: { get: () => 25 } },
        },
    });

    return wrapper.findComponent(AssetBrowser).vm;
}

function popTo(state) {
    window.dispatchEvent(new PopStateEvent('popstate', { state }));
    return wrapper.vm.$nextTick();
}

test('opening an asset pushes an entry that keeps the query string', async () => {
    const browser = mountBrowse('/cp/assets/browse/main?filters=abc');

    browser.edit('main::photo.jpg');
    await wrapper.vm.$nextTick();

    expect(window.location.pathname).toBe('/cp/assets/browse/main/photo.jpg/edit');
    expect(window.location.search).toBe('?filters=abc');
    expect(window.history.state.statamic.values.assetBrowser).toEqual({ folder: '/', editing: 'main::photo.jpg' });
});

test('going back and forth closes and opens the asset editor every time', async () => {
    const browser = mountBrowse('/cp/assets/browse/main?filters=abc');
    const listingEntry = window.history.state;

    browser.edit('main::photo.jpg');
    await wrapper.vm.$nextTick();
    const editorEntry = window.history.state;

    const pushSpy = vi.spyOn(window.history, 'pushState');

    for (let i = 0; i < 3; i++) {
        await popTo(listingEntry);
        expect(browser.editedAssetId).toBeNull();

        await popTo(editorEntry);
        expect(browser.editedAssetId).toBe('main::photo.jpg');
    }

    expect(pushSpy).not.toHaveBeenCalled();
    pushSpy.mockRestore();
});

test('going back restores the folder', async () => {
    const browser = mountBrowse('/cp/assets/browse/main');
    const rootEntry = window.history.state;

    browser.selectFolder('photos');
    await wrapper.vm.$nextTick();
    const photosEntry = window.history.state;

    await popTo(rootEntry);
    expect(browser.path).toBe('/');

    await popTo(photosEntry);
    expect(browser.path).toBe('photos');
});
