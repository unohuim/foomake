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
    scope: {
        type: String,
        default: "current",
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["close", "submit", "update:scope"]);

const localScope = ref(props.scope);

const resolvedLabels = computed(() => ({
    title: props.labels.exportTitle ?? "Export",
    description: props.labels.exportDescription ?? "Export records as CSV using the current list state when needed.",
    formatLabel: props.labels.exportFormatLabel ?? "CSV",
    formatDescription: props.labels.exportFormatDescription ?? "Comma-separated values",
    scopeLegend: props.labels.exportScopeLegend ?? "Export Scope",
    currentOptionTitle: props.labels.exportCurrentOptionTitle ?? "Current filters and sort",
    currentOptionDescription: props.labels.exportCurrentOptionDescription ?? "Uses the current search text and sort order from the list.",
    allOptionTitle: props.labels.exportAllOptionTitle ?? "All records",
    allOptionDescription: props.labels.exportAllOptionDescription ?? "Exports every record in the current tenant.",
    cancelLabel: props.labels.exportCancelLabel ?? "Cancel",
    submitLabel: props.labels.exportSubmitLabel ?? "Export CSV",
}));

const updateScope = () => {
    emit("update:scope", localScope.value);
};

watch(
    () => props.scope,
    (value) => {
        if (value !== localScope.value) {
            localScope.value = value;
        }
    },
);
</script>

<template>
    <BaseDrawer
        :open="open"
        labelled-by="resource-export-drawer-title"
        close-label="Close export drawer"
        panel-class="w-screen max-w-md"
        close-button-class="text-white hover:text-[#dbe8d0] focus-visible:outline-white"
        @close="emit('close')"
    >
        <div class="flex h-full flex-col bg-white">
            <div class="export-header-texture relative overflow-hidden bg-[#001f3f] px-5 pb-5 pt-8 text-white sm:px-7">
                <div class="relative flex h-10 w-10 items-center justify-center rounded-full border-2 border-[#6f895d]/60 bg-white/5 sm:h-11 sm:w-11">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0 4.5 4.5M12 4.5 7.5 9M4.5 14.25v3A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25v-3" />
                    </svg>
                </div>

                <h2 id="resource-export-drawer-title" class="relative mt-4 text-lg font-semibold leading-tight text-white sm:text-xl">
                    {{ resolvedLabels.title }}
                </h2>
                <p class="relative mt-2 max-w-sm text-sm leading-6 text-blue-50">
                    {{ resolvedLabels.description }}
                </p>

                <div class="relative mt-4 inline-flex items-center gap-2 rounded-md bg-[#6f895d]/25 px-3 py-1.5 text-xs font-semibold text-[#e6efd9]">
                    <svg class="h-4 w-4 text-[#dbe8d0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>Ready to export</span>
                </div>
            </div>

            <div class="h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-7 sm:py-5">
                <div class="space-y-5">
                    <section>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-[#001f3f]">
                            Format
                        </h3>

                        <div class="mt-3 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">
                            <div class="flex h-9 w-8 shrink-0 flex-col overflow-hidden rounded border border-[#6f895d] bg-white text-center shadow-sm">
                                <div class="flex flex-1 items-center justify-center">
                                    <svg class="h-4 w-4 text-[#6f895d]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-7.5L14.25 1.5H6.75A2.25 2.25 0 0 0 4.5 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 1.5v5.25h5.25" />
                                    </svg>
                                </div>
                                <div class="bg-[#6f895d] px-0.5 py-px text-[0.5rem] font-bold leading-none text-white">
                                    CSV
                                </div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-950">
                                    {{ resolvedLabels.formatLabel }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-600">
                                    {{ resolvedLabels.formatDescription }}
                                </p>
                            </div>

                        </div>
                    </section>

                    <fieldset>
                        <legend class="text-sm font-bold uppercase tracking-wide text-[#001f3f]">
                            {{ resolvedLabels.scopeLegend }}
                        </legend>

                        <label
                            class="cursor-pointer mt-3 flex items-center gap-3 rounded-lg border p-3 transition"
                            :class="localScope === 'current' ? 'border-[#6f895d] bg-[#6f895d]/10 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'"
                        >
                            <input
                                v-model="localScope"
                                type="radio"
                                class="h-5 w-5 border-gray-400 text-[#6f895d] focus:ring-[#6f895d]"
                                value="current"
                                @change="updateScope"
                            >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#6f895d]/10 text-[#6f895d] sm:h-12 sm:w-12">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5M6.75 10.5h10.5M9.75 15.75h4.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5 10.5 12v5.25l3-1.5V12l6-7.5" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-950 sm:text-base">{{ resolvedLabels.currentOptionTitle }}</span>
                                <span class="mt-1 block max-w-xs text-xs leading-5 text-gray-600">{{ resolvedLabels.currentOptionDescription }}</span>
                            </span>
                        </label>

                        <label
                            class="cursor-pointer mt-3 flex items-center gap-3 rounded-lg border p-3 transition"
                            :class="localScope === 'all' ? 'border-[#6f895d] bg-[#6f895d]/10 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'"
                        >
                            <input
                                v-model="localScope"
                                type="radio"
                                class="h-5 w-5 border-gray-400 text-[#6f895d] focus:ring-[#6f895d]"
                                value="all"
                                @change="updateScope"
                            >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600 sm:h-12 sm:w-12">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5c4.142 0 7.5-1.343 7.5-3S16.142 1.5 12 1.5 4.5 2.843 4.5 4.5 7.858 7.5 12 7.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5v5.25c0 1.657 3.358 3 7.5 3s7.5-1.343 7.5-3V4.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75V15c0 1.657 3.358 3 7.5 3s7.5-1.343 7.5-3V9.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15v4.5c0 1.657 3.358 3 7.5 3s7.5-1.343 7.5-3V15" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-950 sm:text-base">{{ resolvedLabels.allOptionTitle }}</span>
                                <span class="mt-1 block max-w-xs text-xs leading-5 text-gray-600">{{ resolvedLabels.allOptionDescription }}</span>
                            </span>
                        </label>
                    </fieldset>

                    <slot name="error" :error="error">
                        <p v-if="error" class="text-sm text-red-600">
                            {{ error }}
                        </p>
                    </slot>
                </div>
            </div>

            <div class="grid shrink-0 grid-cols-2 gap-3 border-t border-gray-100 bg-white px-5 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.06)] sm:px-7 sm:py-4">
                <button
                    type="button"
                    class="cursor-pointer inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-[#001f3f] shadow-sm transition hover:bg-gray-50"
                    @click="emit('close')"
                >
                    {{ resolvedLabels.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-transparent bg-[#6f895d] px-4 text-sm font-semibold text-white shadow-sm transition hover:brightness-105"
                    :disabled="submitting"
                    :class="submitting ? 'cursor-not-allowed opacity-60' : ''"
                    @click="emit('submit', localScope)"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0 4.5 4.5M12 4.5 7.5 9M4.5 14.25v3A2.25 2.25 0 0 0 6.75 19.5h10.5a2.25 2.25 0 0 0 2.25-2.25v-3" />
                    </svg>
                    Export
                </button>
            </div>
        </div>
    </BaseDrawer>
</template>

<style scoped>
.export-header-texture::before {
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
