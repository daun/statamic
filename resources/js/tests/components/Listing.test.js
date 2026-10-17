import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';
import { defineComponent, h } from 'vue';
import * as Globals from '@/bootstrap/globals';
import Listing, { injectListingContext } from '@/components/ui/Listing/Listing.vue';

Object.keys(Globals).forEach((fn) => (window[fn] = Globals[fn]));

window.Statamic = {
    $config: { get: () => undefined },
    $progress: { loading: () => {}, complete: () => {} },
    $preferences: { get: () => undefined },
    $events: { $on: () => {}, $off: () => {}, $emit: () => {} },
};

const Probe = defineComponent({
    setup() {
        return { listing: injectListingContext() };
    },
    render() {
        return h('div');
    },
});

function mountListing(props) {
    return mount(Listing, {
        props: {
            items: [],
            ...props,
        },
        slots: { default: () => h(Probe) },
    });
}

test('counts active filter badges that belong to the listing filters', () => {
    const wrapper = mountListing({ filters: [{ handle: 'author', auto_apply: [] }, { handle: 'status', auto_apply: [] }] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.activeFilterBadges.value = { author: 'John', status: 'Published' };

    expect(listing.activeFilterBadgeCount.value).toBe(2);
});

test('ignores active filter badges that are not configured filters', () => {
    const wrapper = mountListing({ filters: [{ handle: 'author', auto_apply: [] }] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.activeFilterBadges.value = { author: 'John', site: { site: 'default' } };

    expect(listing.activeFilterBadgeCount.value).toBe(1);
});

test('counts each nested field badge individually', () => {
    const wrapper = mountListing({ filters: [{ handle: 'fields', auto_apply: [] }] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.activeFilterBadges.value = { fields: { title: 'Foo', slug: 'bar' } };

    expect(listing.activeFilterBadgeCount.value).toBe(2);
});

test('pushing query parameters preserves other history state but drops inertia page state', async () => {
    window.history.replaceState({ page: { component: 'Foo' }, path: 'photos' }, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.setSearchQuery('cats');
    await wrapper.vm.$nextTick();

    expect(window.location.search).toContain('search=cats');
    expect(window.history.state.path).toBe('photos');
    expect(window.history.state.page).toBeUndefined();
    expect(window.history.state.parameters.search).toBe('cats');

    wrapper.unmount();
});

test('popping state resets the search and filters when they are absent from the stored parameters', async () => {
    window.history.replaceState(null, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'], filters: [{ handle: 'status', auto_apply: [] }] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.setSearchQuery('cats');
    listing.setFilter('status', { value: 'published' });
    await wrapper.vm.$nextTick();

    window.dispatchEvent(new PopStateEvent('popstate', { state: { parameters: { page: 1 } } }));
    await wrapper.vm.$nextTick();

    expect(listing.searchQuery.value).toBeNull();
    expect(listing.activeFilters.value).toEqual({});

    wrapper.unmount();
});

test('popping state without listing parameters is ignored', async () => {
    window.history.replaceState(null, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.setSearchQuery('cats');
    await wrapper.vm.$nextTick();

    window.dispatchEvent(new PopStateEvent('popstate', { state: { path: 'photos' } }));
    await wrapper.vm.$nextTick();

    expect(listing.searchQuery.value).toBe('cats');

    wrapper.unmount();
});
