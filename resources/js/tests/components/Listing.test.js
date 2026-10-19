import { mount } from '@vue/test-utils';
import { expect, test, vi } from 'vitest';
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

test('pushing query parameters stores them in the history entry', async () => {
    window.history.replaceState(null, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'] });
    const { listing } = wrapper.findComponent(Probe).vm;

    listing.setSearchQuery('cats');
    await wrapper.vm.$nextTick();

    expect(window.location.search).toContain('search=cats');
    expect(window.history.state.statamic.values.parameters.search).toBe('cats');

    wrapper.unmount();
});

test('popping an entry resets the search and filters when they are absent from the stored parameters', async () => {
    window.history.replaceState(null, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'], filters: [{ handle: 'status', auto_apply: [] }] });
    const { listing } = wrapper.findComponent(Probe).vm;
    const { session } = window.history.state.statamic;

    listing.setSearchQuery('cats');
    listing.setFilter('status', { value: 'published' });
    await wrapper.vm.$nextTick();

    window.dispatchEvent(new PopStateEvent('popstate', { state: { statamic: { session, values: { parameters: { page: 1 } } } } }));
    await wrapper.vm.$nextTick();

    expect(listing.searchQuery.value).toBeNull();
    expect(listing.activeFilters.value).toEqual({});

    wrapper.unmount();
});

test('popping an entry does not push another one', async () => {
    window.history.replaceState(null, '', '/');
    const wrapper = mountListing({ pushQuery: true, columns: ['title'] });
    const { listing } = wrapper.findComponent(Probe).vm;
    const { session } = window.history.state.statamic;
    const pushSpy = vi.spyOn(window.history, 'pushState');

    window.dispatchEvent(new PopStateEvent('popstate', { state: { statamic: { session, values: { parameters: { search: 'dogs' } } } } }));
    await wrapper.vm.$nextTick();

    expect(listing.searchQuery.value).toBe('dogs');
    expect(pushSpy).not.toHaveBeenCalled();

    pushSpy.mockRestore();
    wrapper.unmount();
});
