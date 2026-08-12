<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({
    labels: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    records: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: "",
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: "",
    },
    boundedHeightClass: {
        type: String,
        default: "h-[calc(100vh-8rem)]",
    },
});

const emit = defineEmits([
    "update:search",
    "search",
    "create",
    "import",
    "export",
]);

const localSearch = ref(props.search);
const searchTimer = ref(null);

const resolvedLabels = computed(() => ({
    searchPlaceholder: props.labels.searchPlaceholder ?? "Search",
    exportTitle: props.labels.exportTitle ?? "Export",
    exportAriaLabel: props.labels.exportAriaLabel ?? "Export",
    importTitle: props.labels.importTitle ?? "Import",
    importAriaLabel: props.labels.importAriaLabel ?? "Import",
    createTitle: props.labels.createTitle ?? "Create",
    createAriaLabel: props.labels.createAriaLabel ?? "Create",
    emptyState: props.labels.emptyState ?? "No records found.",
}));

const resolvedPermissions = computed(() => ({
    showExport: Boolean(props.permissions.showExport),
    showImport: Boolean(props.permissions.showImport),
    showCreate: props.permissions.showCreate !== false,
}));

const hasRecords = computed(() => props.records.length > 0);

const updateSearch = () => {
    emit("update:search", localSearch.value);

    if (searchTimer.value) {
        window.clearTimeout(searchTimer.value);
    }

    searchTimer.value = window.setTimeout(() => {
        emit("search", localSearch.value);
        searchTimer.value = null;
    }, 200);
};

watch(
    () => props.search,
    (value) => {
        if (value !== localSearch.value) {
            localSearch.value = value;
        }
    },
);

onBeforeUnmount(() => {
    if (searchTimer.value) {
        window.clearTimeout(searchTimer.value);
    }
});
</script>

<template>
    <section
        class="resource-index-shell flex min-h-0 w-full flex-col overflow-hidden"
        :class="boundedHeightClass"
        data-resource-index
    >
        <div class="flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden">
            <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-white md:border-y md:border-gray-200 md:shadow-sm">
                <div class="border-b border-gray-100 bg-white px-4 py-3 md:px-6" data-resource-index-toolbar>
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1" data-resource-index-search>
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.95 7.95 0 1 0 5.4 5.4a7.95 7.95 0 0 0 11.25 11.25Z" />
                                </svg>
                            </div>

                            <input
                                v-model="localSearch"
                                type="search"
                                class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :placeholder="resolvedLabels.searchPlaceholder"
                                :aria-label="resolvedLabels.searchPlaceholder"
                                @input="updateSearch"
                            >

                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-opacity duration-150"
                                :class="loading ? 'opacity-100' : 'opacity-0'"
                                aria-hidden="true"
                            >
                                <svg class="h-4 w-4 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </div>
                        </div>

                        <slot name="toolbar-actions-before" />

                        <button
                            v-if="resolvedPermissions.showExport"
                            type="button"
                            class="cursor-pointer inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-9 sm:w-9"
                            :title="resolvedLabels.exportTitle"
                            :aria-label="resolvedLabels.exportAriaLabel"
                            data-resource-index-export-button
                            @click="emit('export')"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5A2.25 2.25 0 0 0 5.25 10.5v9A2.25 2.25 0 0 0 7.5 21.75h9A2.25 2.25 0 0 0 18.75 19.5v-9A2.25 2.25 0 0 0 16.5 8.25H15M12 15V3m0 12 3.75-3.75M12 15l-3.75-3.75" />
                            </svg>
                        </button>

                        <button
                            v-if="resolvedPermissions.showImport"
                            type="button"
                            class="cursor-pointer inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-9 sm:w-9"
                            :title="resolvedLabels.importTitle"
                            :aria-label="resolvedLabels.importAriaLabel"
                            data-resource-index-import-button
                            @click="emit('import')"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M3.75 19.5h16.5" />
                            </svg>
                        </button>

                        <button
                            v-if="resolvedPermissions.showCreate"
                            type="button"
                            class="cursor-pointer inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-9 sm:w-9"
                            :title="resolvedLabels.createTitle"
                            :aria-label="resolvedLabels.createAriaLabel"
                            data-resource-index-create-button
                            @click="emit('create')"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>

                        <slot name="toolbar-actions-after" />
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto" data-resource-index-records-scroll>
                    <slot
                        v-if="error"
                        name="error"
                        :error="error"
                    >
                        <div class="m-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ error }}
                        </div>
                    </slot>

                    <slot
                        v-else-if="loading && !hasRecords"
                        name="loading"
                    >
                        <div class="p-4 sm:p-6">
                            <div class="space-y-3">
                                <div class="h-20 animate-pulse rounded-lg bg-gray-100" />
                                <div class="h-20 animate-pulse rounded-lg bg-gray-100" />
                                <div class="h-20 animate-pulse rounded-lg bg-gray-100" />
                            </div>
                        </div>
                    </slot>

                    <slot
                        v-else-if="!hasRecords"
                        name="empty"
                    >
                        <div class="p-4 sm:p-6">
                            <div class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                                {{ resolvedLabels.emptyState }}
                            </div>
                        </div>
                    </slot>

                    <div
                        v-else
                        class="transition-opacity duration-150"
                        :class="loading ? 'opacity-80' : 'opacity-100'"
                    >
                        <slot :records="records" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@media (max-width: 767.98px) {
    .resource-index-shell {
        left: 50%;
        position: relative;
        transform: translateX(-50%);
        width: 100vw;
    }
}
</style>
