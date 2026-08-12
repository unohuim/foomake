<script setup>
import { computed, ref, watch } from "vue";

import BaseDrawer from "./BaseDrawer.vue";

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    labels: {
        type: Object,
        default: () => ({}),
    },
    sources: {
        type: Array,
        default: () => [],
    },
    selectedSource: {
        type: String,
        default: "",
    },
    previewRows: {
        type: Array,
        default: () => [],
    },
    loadingPreview: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    previewSearch: {
        type: String,
        default: "",
    },
    showDuplicateRows: {
        type: Boolean,
        default: false,
    },
    duplicateRowCount: {
        type: Number,
        default: 0,
    },
    showBulkOptions: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "close",
    "submit",
    "source-change",
    "file-change",
    "update:selectedSource",
    "update:previewSearch",
    "update:showDuplicateRows",
    "toggle-row-selection",
    "toggle-visible-selection",
]);

const localSelectedSource = ref(props.selectedSource);
const localPreviewSearch = ref(props.previewSearch);
const localShowDuplicateRows = ref(props.showDuplicateRows);
const bulkOptionsOpen = ref(true);
const previewOpen = ref(true);

const resolvedLabels = computed(() => ({
    title: props.labels.title ?? "Import",
    description: props.labels.previewDescription ?? "Review the import preview before confirming the selected records.",
    source: props.labels.source ?? "Source",
    previewSearch: props.labels.previewSearch ?? "Search preview records",
    submitLabel: props.labels.submit ?? "Import Selected",
    cancelLabel: props.labels.cancel ?? "Cancel",
    emptyStateTitle: props.labels.emptyStateTitle ?? "Choose an import source",
    emptyStateDescription: props.labels.emptyStateDescription ?? "Select a connection or file upload source to start loading an import preview.",
    previewEmptyTitle: props.labels.previewEmptyTitle ?? "No preview records",
    previewEmptyDescription: props.labels.previewEmptyDescription ?? "No records match the current import preview filters.",
    loadingPreview: props.labels.loadingPreviewDefault ?? "Loading preview...",
}));

const hasSource = computed(() => localSelectedSource.value !== "");
const hasPreviewRows = computed(() => props.previewRows.length > 0);

const sourceLabel = (source) => {
    if (source.enabled === false && source.disabledLabel) {
        return source.disabledLabel;
    }

    return source.label ?? source.name ?? source.value;
};

const updateSource = () => {
    emit("update:selectedSource", localSelectedSource.value);
    emit("source-change", localSelectedSource.value);
};

const updatePreviewSearch = () => {
    emit("update:previewSearch", localPreviewSearch.value);
};

const updateShowDuplicateRows = () => {
    emit("update:showDuplicateRows", localShowDuplicateRows.value);
};

watch(
    () => props.selectedSource,
    (value) => {
        if (value !== localSelectedSource.value) {
            localSelectedSource.value = value;
        }
    },
);

watch(
    () => props.previewSearch,
    (value) => {
        if (value !== localPreviewSearch.value) {
            localPreviewSearch.value = value;
        }
    },
);

watch(
    () => props.showDuplicateRows,
    (value) => {
        if (value !== localShowDuplicateRows.value) {
            localShowDuplicateRows.value = value;
        }
    },
);
</script>

<template>
    <BaseDrawer
        :open="open"
        labelled-by="resource-import-drawer-title"
        close-label="Close import drawer"
        panel-class="w-screen max-w-3xl"
        close-button-class="text-white hover:text-[#dbe8d0] focus-visible:outline-white"
        @close="emit('close')"
    >
        <div class="flex h-full flex-col divide-y divide-gray-200">
            <div class="import-header-texture relative overflow-hidden bg-[#001f3f] px-6 pb-6 pt-10 text-white sm:px-8">
                <div class="relative flex h-11 w-11 items-center justify-center rounded-full border-2 border-[#6f895d]/60 bg-white/5">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12 4.5 4.5M12 3 7.5 7.5M4.5 15v3A2.25 2.25 0 0 0 6.75 20.25h10.5A2.25 2.25 0 0 0 19.5 18v-3" />
                    </svg>
                </div>

                <div class="relative pr-10">
                    <h2 id="resource-import-drawer-title" class="mt-4 text-xl font-semibold leading-tight text-white">
                        {{ resolvedLabels.title }}
                    </h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-50">
                        {{ resolvedLabels.description }}
                    </p>
                </div>
            </div>

            <div class="h-0 flex-1 overflow-y-auto p-6">
                <div class="flex min-h-0 flex-1 flex-col gap-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <label class="block text-sm font-medium text-gray-700" for="resource-import-source">
                            {{ resolvedLabels.source }}
                            <select
                                id="resource-import-source"
                                v-model="localSelectedSource"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                                data-resource-import-source
                                @change="updateSource"
                            >
                                <option value="">
                                    Select source
                                </option>
                                <option
                                    v-for="source in sources"
                                    :key="source.value"
                                    :value="source.value"
                                    :disabled="source.enabled === false"
                                >
                                    {{ sourceLabel(source) }}
                                </option>
                            </select>
                        </label>

                        <input
                            id="resource-import-file"
                            type="file"
                            accept=".csv,text/csv"
                            class="sr-only"
                            data-resource-import-file-input
                            @change="emit('file-change', $event)"
                        >

                        <slot name="source-errors" :errors="errors">
                            <p v-if="errors.source?.[0]" class="mt-1 text-sm text-red-600">
                                {{ errors.source[0] }}
                            </p>
                            <p v-if="errors.file?.[0]" class="mt-1 text-sm text-red-600">
                                {{ errors.file[0] }}
                            </p>
                        </slot>
                    </div>

                    <slot v-if="hasSource" name="connection-required" />

                    <slot v-if="!hasSource" name="empty-source">
                        <div class="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                <svg class="h-7 w-7 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V7.5m0 0L8.25 11.25M12 7.5l3.75 3.75M3.75 16.5v1.125c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V16.5" />
                                </svg>
                            </div>
                            <h3 class="mt-6 text-base font-semibold text-gray-900">
                                {{ resolvedLabels.emptyStateTitle }}
                            </h3>
                            <p class="mt-2 max-w-md text-sm text-gray-600">
                                {{ resolvedLabels.emptyStateDescription }}
                            </p>
                        </div>
                    </slot>

                    <div v-if="hasSource && showBulkOptions" class="rounded-lg border border-gray-200 bg-white">
                        <button
                            type="button"
                            class="cursor-pointer flex w-full items-center justify-between px-4 py-4 text-left"
                            data-resource-import-bulk-options-accordion
                            @click="bulkOptionsOpen = !bulkOptionsOpen"
                        >
                            <span class="text-sm font-semibold text-gray-900">Bulk Import Options</span>
                            <svg class="h-5 w-5 text-gray-400 transition" :class="bulkOptionsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-show="bulkOptionsOpen" class="border-t border-gray-100 px-4 py-4">
                            <slot name="bulk-options" />
                        </div>
                    </div>

                    <div v-if="hasSource" class="rounded-lg border border-gray-200 bg-white">
                        <button
                            type="button"
                            class="cursor-pointer flex w-full items-center justify-between px-4 py-4 text-left"
                            data-resource-import-preview-records-accordion
                            @click="previewOpen = !previewOpen"
                        >
                            <span class="text-sm font-semibold text-gray-900">Import Preview</span>
                            <svg class="h-5 w-5 text-gray-400 transition" :class="previewOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div v-show="previewOpen" class="border-t border-gray-100">
                            <div class="space-y-4 px-4 py-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <label class="block min-w-0 flex-1 text-sm text-gray-700">
                                        <span class="sr-only">{{ resolvedLabels.previewSearch }}</span>
                                        <input
                                            v-model="localPreviewSearch"
                                            type="search"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                                            :placeholder="resolvedLabels.previewSearch"
                                            data-resource-import-preview-search
                                            @input="updatePreviewSearch"
                                        >
                                    </label>
                                    <label class="cursor-pointer inline-flex max-w-full items-center gap-2 text-sm text-gray-700">
                                        <input
                                            v-model="localShowDuplicateRows"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-[#6f895d] shadow-sm focus:ring-[#6f895d]"
                                            data-resource-import-show-duplicates
                                            @change="updateShowDuplicateRows"
                                        >
                                        <span class="truncate">Show Duplicates ({{ duplicateRowCount }} rows)</span>
                                    </label>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="cursor-pointer inline-flex items-center gap-3 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-[#6f895d] shadow-sm focus:ring-[#6f895d]"
                                            data-resource-import-select-visible
                                            @change="emit('toggle-visible-selection', $event)"
                                        >
                                        Select All
                                    </label>
                                    <p v-if="!localShowDuplicateRows && duplicateRowCount > 0" class="text-sm text-gray-500">
                                        Duplicate rows are hidden until enabled.
                                    </p>
                                </div>

                                <slot name="preview-errors" :errors="errors">
                                    <p v-if="errors.preview?.[0]" class="text-sm text-red-600">
                                        {{ errors.preview[0] }}
                                    </p>
                                    <p v-if="errors.import?.[0]" class="text-sm text-red-600">
                                        {{ errors.import[0] }}
                                    </p>
                                </slot>
                            </div>

                            <div class="max-h-[32rem] overflow-y-auto px-4 pb-32">
                                <slot v-if="loadingPreview" name="preview-loading">
                                    <div class="space-y-4">
                                        <div class="rounded-lg border border-[#6f895d]/20 bg-[#6f895d]/10 p-4 text-sm text-[#4f6642]">
                                            {{ resolvedLabels.loadingPreview }}
                                        </div>
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200" />
                                                <div class="mt-3 h-4 w-2/3 animate-pulse rounded bg-gray-100" />
                                            </div>
                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                <div class="h-4 w-1/4 animate-pulse rounded bg-gray-200" />
                                                <div class="mt-3 h-4 w-3/4 animate-pulse rounded bg-gray-100" />
                                            </div>
                                        </div>
                                    </div>
                                </slot>

                                <slot v-else-if="!hasPreviewRows" name="preview-empty">
                                    <div class="flex min-h-52 items-center justify-center px-4 py-12">
                                        <div class="w-full max-w-md rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-10 text-center">
                                            <h3 class="text-sm font-semibold text-gray-900">
                                                {{ resolvedLabels.previewEmptyTitle }}
                                            </h3>
                                            <p class="mt-2 text-sm text-gray-600">
                                                {{ resolvedLabels.previewEmptyDescription }}
                                            </p>
                                        </div>
                                    </div>
                                </slot>

                                <div v-else class="space-y-2">
                                    <slot name="preview-rows" :rows="previewRows">
                                        <article
                                            v-for="row in previewRows"
                                            :key="row.id ?? row.external_id ?? row.name"
                                            class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
                                        >
                                            <div class="flex min-h-10 items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    class="shrink-0 rounded border-gray-300 text-[#6f895d] shadow-sm focus:ring-[#6f895d]"
                                                    :checked="Boolean(row.selected)"
                                                    :disabled="row.is_duplicate"
                                                    @change="emit('toggle-row-selection', { row, event: $event })"
                                                >
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium text-gray-900">
                                                        {{ row.name ?? row.title ?? "Preview row" }}
                                                    </p>
                                                    <p v-if="row.subtitle" class="truncate text-xs text-gray-500">
                                                        {{ row.subtitle }}
                                                    </p>
                                                </div>
                                            </div>
                                        </article>
                                    </slot>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 justify-end gap-3 px-6 py-4">
                <slot name="footer" :submitting="submitting">
                    <button
                        type="button"
                        class="cursor-pointer inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >
                        {{ resolvedLabels.cancelLabel }}
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer inline-flex items-center rounded-md border border-transparent bg-[#6f895d] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:brightness-105"
                        :disabled="submitting"
                        :class="submitting ? 'cursor-not-allowed opacity-60' : ''"
                        @click="emit('submit')"
                    >
                        {{ resolvedLabels.submitLabel }}
                    </button>
                </slot>
            </div>
        </div>
    </BaseDrawer>
</template>

<style scoped>
.import-header-texture::before {
    background-image:
        radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.18) 1px, transparent 0),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 12px),
        linear-gradient(135deg, rgba(255, 255, 255, 0.07), transparent 48%);
    background-size: 16px 16px, 18px 18px, 100% 100%;
    content: "";
    inset: 0;
    opacity: 0.75;
    pointer-events: none;
    position: absolute;
}
</style>
