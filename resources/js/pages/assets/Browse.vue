<script setup>
import Head from '@/pages/layout/Head.vue';
import { DocsCallout } from '@ui';
import { computed, onMounted, ref } from 'vue';
import useHistoryState from '@/composables/history-state.js';

const props = defineProps(['container', 'folder', 'columns', 'filters', 'canCreateContainers', 'createContainerUrl', 'editing']);

const browser = ref(null);
const path = ref(props.folder);
const selectedAssets = ref([]);

const headTitle = computed(() => {
    const title = __(props.container.title);
    const section = __('Assets');

    // Avoid duplicating when the container is named "Assets".
    return title === section ? section : [title, section];
});

// The folder and the asset being edited are stored in the browser history,
// so the back and forward buttons move between folders and the asset editor.
const historyState = useHistoryState('assetBrowser', ({ folder, editing }) => {
    if (folder !== browser.value.path) path.value = folder;
    browser.value.edit(editing);
});

onMounted(() => historyState.replace(currentState()));

function currentState() {
    return { folder: browser.value.path, editing: browser.value.editedAssetId ?? null };
}

function editAsset(asset) {
    event.preventDefault();
    browser.value.edit(asset.id);
}

/**
 * When a user has navigated to another folder, or opened or closed the asset editor.
 */
function navigate(newPath) {
    const previousPath = path.value;

    path.value = newPath;

    let url = cp_url('assets/browse/' + props.container.id);
    if (newPath !== '/') url += '/' + newPath;
    historyState.push(currentState(), url + window.location.search);

    // Clear out any selections. It would be confusing to navigate to a different
    // folder, perform an action, and discover you performed it on an asset that
    // was still selected, but no longer visible. We only want to do this when
    // navigating to/from folders, not when navigating between assets.
    if (!newPath.includes('/edit') && !previousPath.includes('/edit')) {
        selectedAssets.value = [];
    }
}

/**
 * When selections are changed, we need them reflected here.
 */
function updateSelections(selections) {
    selectedAssets.value = selections;
}
</script>

<template>
    <div class="h-full" v-cloak>
        <Head :title="headTitle" />

        <asset-browser
            ref="browser"
            :can-create-containers="canCreateContainers"
            :create-container-url="createContainerUrl"
            :container="container"
            :initial-per-page="$config.get('paginationSize')"
            :initial-editing-asset-id="editing"
            :selected-path="path"
            :selected-assets="selectedAssets"
            :initial-columns="columns"
            :filters="filters"
            push-query
            @navigated="navigate"
            @selections-updated="updateSelections"
            @edit-asset="editAsset"
        >
            <template #initializing>
                <!-- Header Skeleton -->
                <div class="flex justify-between py-8">
                    <ui-skeleton class="h-8 w-95" />
                    <div class="flex gap-2 sm:gap-3">
                        <ui-skeleton class="h-10 w-26" />
                        <ui-skeleton class="h-10 w-36" />
                        <ui-skeleton class="h-10 w-20" />
                    </div>
                </div>
                <!-- Toolbar Skeleton -->
                <div class="flex justify-between py-3">
                    <ui-skeleton class="h-9 w-95" />
                    <ui-skeleton class="h-9 w-10" />
                </div>
                <!-- Assets grid Skeleton -->
                <div class="flex justify-between">
                    <ui-skeleton class="h-30 w-full" />
                </div>
            </template>
            <template #footer>
                <div class="starting-style-transition starting-style-transition--delay">
                    <DocsCallout :topic="__('Assets')" url="assets" />
                </div>
            </template>
        </asset-browser>
    </div>
</template>
