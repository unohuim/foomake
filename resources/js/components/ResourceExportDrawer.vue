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
        @close="emit('close')"
    >
        <div class="flex h-full flex-col divide-y divide-gray-200">
            <div class="h-0 flex-1 overflow-y-auto p-6">
                <div class="pr-10">
                    <h2 id="resource-export-drawer-title" class="text-lg font-medium text-gray-900">
                        {{ resolvedLabels.title }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ resolvedLabels.description }}
                    </p>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">
                            Format
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ resolvedLabels.formatLabel }}
                        </p>
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium text-gray-700">
                            {{ resolvedLabels.scopeLegend }}
                        </legend>

                        <label class="cursor-pointer flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                            <input
                                v-model="localScope"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500"
                                value="current"
                                @change="updateScope"
                            >
                            <span>
                                <span class="block font-medium text-gray-900">{{ resolvedLabels.currentOptionTitle }}</span>
                                <span class="mt-1 block text-gray-600">{{ resolvedLabels.currentOptionDescription }}</span>
                            </span>
                        </label>

                        <label class="cursor-pointer flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                            <input
                                v-model="localScope"
                                type="radio"
                                class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500"
                                value="all"
                                @change="updateScope"
                            >
                            <span>
                                <span class="block font-medium text-gray-900">{{ resolvedLabels.allOptionTitle }}</span>
                                <span class="mt-1 block text-gray-600">{{ resolvedLabels.allOptionDescription }}</span>
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

            <div class="flex shrink-0 justify-end gap-3 px-6 py-4">
                <button
                    type="button"
                    class="cursor-pointer inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                    @click="emit('close')"
                >
                    {{ resolvedLabels.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                    :disabled="submitting"
                    :class="submitting ? 'cursor-not-allowed opacity-60' : ''"
                    @click="emit('submit', localScope)"
                >
                    {{ resolvedLabels.submitLabel }}
                </button>
            </div>
        </div>
    </BaseDrawer>
</template>
